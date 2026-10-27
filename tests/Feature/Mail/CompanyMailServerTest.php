<?php

declare(strict_types=1);

use App\Mail\ServiceReminderMail;
use App\Mail\VisitRecapMail;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Notifications\OpsAlert;
use App\Services\TenantMailer;
use App\Support\MailHost;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/*
 * A company's emails to its customers go out through its own mail server once a
 * test email got through; if that server can't send one, it goes out from
 * RoutePilot instead and the company is told. Two fake servers stand in here:
 * `company_ok` (keeps what it's sent) and `company_down` (refuses everything).
 */

beforeEach(function () {
    config([
        'mail.mailers.company_ok' => ['transport' => 'array'],
        'mail.mailers.company_down' => ['transport' => 'failing'],
        'mail.from' => ['address' => 'contact@routepilot.test', 'name' => 'RoutePilot'],
    ]);
    Mail::extend('failing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SymfonySentMessage $message): void
        {
            throw new TransportException('Connection could not be established with host "smtp.glynn.test:587"');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });

    $this->tenant = Tenant::factory()->create(['name' => 'Glynn Pool Care']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Jane', 'email' => 'jane@example.com']);
    $pool = Pool::factory()->for($this->tenant)->for($customer)->create();
    $this->visit = ServiceVisit::factory()->for($this->tenant)->for($pool)->create([
        'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
        'status' => 'completed', 'completed_at' => now(), 'visited_at' => now(),
    ]);
});

/** Route this test's company mail to one of the fake servers. */
function companyServer(string $mailer): void
{
    app()->instance(TenantMailer::class, new class($mailer) extends TenantMailer
    {
        public function __construct(private string $to) {}

        public function prepare(?int $tenantId, bool $evenIfOff = false): array
        {
            return $tenantId === null ? parent::prepare(null) : ['mailer' => $this->to, 'from' => ['address' => 'office@glynn.test', 'name' => '']];
        }
    });
}

/** @return list<Email> what a mailer's array transport kept */
function sentThrough(string $mailer): array
{
    return Mail::mailer($mailer)->getSymfonyTransport()->messages()
        ->map(fn ($m) => $m->getOriginalMessage())->filter(fn (RawMessage $m) => $m instanceof Email)->values()->all();
}

function smtpRow(array $config, bool $on): Integration
{
    $row = new Integration(['provider' => 'smtp', 'integration_type' => 'smtp', 'is_active' => $on, 'config' => $config]);
    $row->forceFill(['tenant_id' => test()->tenant->id])->save();

    return $row;
}

test('with its server on, the company’s emails go out through it, from its own address', function () {
    companyServer('company_ok');

    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit, 0.0, null));
    Mail::to('jane@example.com')->queue(new ServiceReminderMail('Jane', 'Glynn Pool Care', 'Backyard pool', 'Tuesday', null, $this->tenant)); // the worker's path

    $sent = sentThrough('company_ok');
    expect($sent)->toHaveCount(2)
        ->and($sent[0]->getFrom()[0]->getAddress())->toBe('office@glynn.test')
        ->and($sent[0]->getFrom()[0]->getName())->toBe('Glynn Pool Care')
        ->and(sentThrough('array'))->toBe([]);
});

test('without a server of its own, nothing changes: RoutePilot sends it', function () {
    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit, 0.0, null));

    expect(sentThrough('array'))->toHaveCount(1)
        ->and(sentThrough('array')[0]->getFrom()[0]->getAddress())->toBe('contact@routepilot.test');
});

test('if its server can’t send, RoutePilot sends it instead, replies still reach the company, and the admins hear once', function () {
    Notification::fake();
    companyServer('company_down');

    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit, 0.0, null));
    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit, 0.0, null));

    $sent = sentThrough('array');
    expect($sent)->toHaveCount(2)
        ->and($sent[0]->getFrom()[0]->getAddress())->toBe('contact@routepilot.test')
        ->and(array_map(fn ($a) => $a->getAddress(), $sent[0]->getReplyTo()))->toBe(['office@glynn.test'])
        ->and(iterator_to_array($sent[0]->getHeaders()->all('list-unsubscribe'), false))->toHaveCount(1); // a fresh copy, not one prepared twice

    $failure = json_decode((string) TenantSetting::getFor($this->tenant->id, TenantMailer::FAILURE_KEY), true);
    expect($failure['reason'])->toBe('RoutePilot couldn’t reach your mail server.');
    Notification::assertSentToTimes($this->admin, OpsAlert::class, 1);
    Notification::assertSentTo($this->admin, OpsAlert::class, fn (OpsAlert $n) => $n->kind === 'mail_failed' && $n->url === '/company?tab=email');

    // Back up: the next email through it clears the failure.
    companyServer('company_ok');
    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit, 0.0, null));
    expect(TenantSetting::getFor($this->tenant->id, TenantMailer::FAILURE_KEY))->toBeNull();
});

test('a fallback keeps the company’s own reply-to address when it has one', function () {
    companyServer('company_down');
    $this->tenant->update(['reply_to_email' => 'replies@glynn.test']);

    Mail::to('jane@example.com')->send(new VisitRecapMail($this->visit->fresh(), 0.0, null));

    expect(array_map(fn ($a) => $a->getAddress(), sentThrough('array')[0]->getReplyTo()))->toBe(['replies@glynn.test']);
});

test('the server is used only once switched on, with its own from address, and a public host', function () {
    $mailer = app(TenantMailer::class);
    $config = ['host' => '203.0.113.10', 'port' => 587, 'from_address' => 'office@glynn.test', 'username' => 'u', 'password' => 'p'];

    $row = smtpRow($config, on: false);
    expect($mailer->prepare($this->tenant->id)['mailer'])->toBe('array')
        ->and($mailer->prepare($this->tenant->id, evenIfOff: true)['mailer'])->toBe('tenant_'.$this->tenant->id);

    $row->update(['is_active' => true, 'config' => [...$config, 'from_address' => null]]);
    expect($mailer->prepare($this->tenant->id)['mailer'])->toBe('array');

    $row->update(['config' => [...$config, 'host' => '192.168.0.10']]);
    expect($mailer->prepare($this->tenant->id)['mailer'])->toBe('array');
});

test('edited settings take effect in a running worker', function () {
    $mailer = app(TenantMailer::class);
    $row = smtpRow(['host' => '203.0.113.10', 'port' => 587, 'from_address' => 'office@glynn.test'], on: true);

    $first = Mail::mailer($mailer->prepare($this->tenant->id)['mailer']);
    $row->update(['config' => ['host' => '203.0.113.20', 'port' => 587, 'from_address' => 'office@glynn.test']]);
    $second = Mail::mailer($mailer->prepare($this->tenant->id)['mailer']);

    expect($second)->not->toBe($first)
        ->and((string) $second->getSymfonyTransport())->toContain('203.0.113.20');
});

test('only a public mail server on a mail port is allowed', function () {
    foreach (['127.0.0.1', '10.0.0.5', '192.168.0.80', '169.254.1.1', '::1', 'localhost'] as $host) {
        expect(MailHost::allowed($host, 587))->toBeFalse();
    }
    expect(MailHost::allowed('203.0.113.10', 8080))->toBeFalse()
        ->and(MailHost::allowed('8.8.8.8', 587))->toBeTrue();

    config(['mail.tenant_private_hosts' => true]); // staging's local mail catcher
    expect(MailHost::allowed('127.0.0.1', 2525))->toBeTrue();
});

test('saving the settings switches the server off until a test gets through', function () {
    $fields = ['host' => '8.8.8.8', 'port' => 587, 'encryption' => 'tls', 'username' => 'u', 'password' => 'p', 'from_address' => 'office@glynn.test', 'from_name' => ''];
    smtpRow(['host' => '8.8.8.8', 'port' => 587, 'from_address' => 'office@glynn.test'], on: true);
    TenantSetting::setFor($this->tenant->id, TenantMailer::FAILURE_KEY, '{"at":"x","reason":"old"}');

    $this->actingAs($this->admin)->patch('/company/mail', [...$fields, 'host' => '127.0.0.1'])->assertSessionHasErrors('host');
    $this->actingAs($this->admin)->patch('/company/mail', [...$fields, 'port' => 8080])->assertSessionHasErrors('port');
    $this->actingAs($this->admin)->patch('/company/mail', [...$fields, 'from_address' => ''])->assertSessionHasErrors('from_address');

    $this->actingAs($this->admin)->patch('/company/mail', $fields)->assertSessionHasNoErrors()->assertSessionHas('success', 'Saved. Send a test email to turn it on.');
    expect(Integration::query()->sole()->is_active)->toBeFalse()
        ->and(TenantSetting::getFor($this->tenant->id, TenantMailer::FAILURE_KEY))->toBeNull();
});

test('a test that gets through switches it on; one that fails leaves it off and says why', function () {
    smtpRow(['host' => '8.8.8.8', 'port' => 587, 'from_address' => 'office@glynn.test'], on: false);

    companyServer('company_down');
    $this->actingAs($this->admin)->post('/company/mail/test')->assertSessionHas('error', 'RoutePilot couldn’t reach your mail server.');
    expect(Integration::query()->sole()->is_active)->toBeFalse()
        ->and(sentThrough('array'))->toBe([]); // no fallback for the test

    companyServer('company_ok');
    $this->actingAs($this->admin)->post('/company/mail/test')->assertSessionHas('success');
    expect(Integration::query()->sole()->is_active)->toBeTrue()
        ->and(sentThrough('company_ok')[0]->getTo()[0]->getAddress())->toBe($this->admin->email)
        ->and(AuditLog::query()->where('action', 'company.mail.tested')->exists())->toBeTrue();

    $this->actingAs($this->admin)->post('/company/mail/off')->assertSessionHas('success');
    expect(Integration::query()->sole()->is_active)->toBeFalse();
});

test('only the company’s admins can test or turn off its mail server', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->post('/company/mail/test')->assertForbidden();
    $this->actingAs($agent)->post('/company/mail/off')->assertForbidden();
});

test('the Email tab shows the last failure', function () {
    TenantSetting::setFor($this->tenant->id, TenantMailer::FAILURE_KEY, (string) json_encode(['at' => '2026-10-26T18:00:00+00:00', 'reason' => 'Your mail server refused the username or password.']));

    $this->actingAs($this->admin)->get('/company')
        ->assertInertia(fn ($page) => $page->where('mail.failure.reason', 'Your mail server refused the username or password.'));
});

test('a failure reason is in words an admin can act on', function () {
    expect(TenantMailer::reason(new TransportException('Failed to authenticate on SMTP server with username "x" using 2 possible authenticators. 535 5.7.8')))
        ->toBe('Your mail server refused the username or password.')
        ->and(TenantMailer::reason(new TransportException('Connection could not be established with host "x": Connection timed out')))
        ->toBe('RoutePilot couldn’t reach your mail server.');
});
