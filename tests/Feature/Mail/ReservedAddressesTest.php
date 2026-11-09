<?php

declare(strict_types=1);

use App\Listeners\BlockReservedAddresses;
use App\Mail\ServiceReminderMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailyOpsChecks;
use Illuminate\Support\Facades\Mail;

/*
 * Sample data uses addresses that can't exist. They must never be sent to, or
 * every demo reminder comes back as a bounce.
 */

/** What actually reached a mailer's transport, addressed to whom. */
function delivered(?string $mailer = null): array
{
    return collect(Mail::mailer($mailer)->getSymfonyTransport()->messages())
        ->flatMap(fn ($sent) => array_map(fn ($a) => $a->getAddress(), $sent->getEnvelope()->getRecipients()))
        ->all();
}

function reminderMail(): ServiceReminderMail
{
    return new ServiceReminderMail('Robert', 'Demo Company', 'Backyard', 'Monday, November 9', 'Marcus');
}

test('it knows which addresses can’t exist', function (string $email, bool $reserved) {
    expect(BlockReservedAddresses::reserved($email))->toBe($reserved);
})->with([
    ['robert@example.test', true],
    ['ROBERT@EXAMPLE.TEST', true],
    ['a@mail.example.test', true],
    ['a@foo.example', true],
    ['a@foo.invalid', true],
    ['a@foo.localhost', true],
    ['a@example.com', true],
    ['a@example.net', true],
    ['a@example.org', true],
    ['jan@gmail.com', false],
    ['a@myexample.com', false],
    ['a@latest.io', false],
    ['a@test.com', false],
    ['a@example.com.au', false],
    ['a@contest', false],
]);

test('an email to a reserved address never reaches the mail server', function () {
    Mail::to('robert@example.test')->send(reminderMail());

    expect(delivered())->toBe([]);
});

test('nor when it’s queued, which is how customer email goes out', function () {
    Mail::to('robert@example.test')->queue(reminderMail());

    expect(delivered())->toBe([]);
});

test('a real address still gets its email', function () {
    Mail::to('jan@gmail.com')->send(reminderMail());

    expect(delivered())->toBe(['jan@gmail.com']);
});

test('one real recipient is enough for it to go out', function () {
    Mail::to('jan@gmail.com')->cc('robert@example.test')->send(reminderMail());

    expect(delivered())->toContain('jan@gmail.com');
});

test('it holds for a company’s own mail server too', function () {
    config(['mail.mailers.company_server' => ['transport' => 'array']]);

    Mail::mailer('company_server')->to('robert@example.test')->send(reminderMail());
    expect(delivered('company_server'))->toBe([]);

    Mail::mailer('company_server')->to('jan@gmail.com')->send(reminderMail());
    expect(delivered('company_server'))->toBe(['jan@gmail.com']);
});

test('the morning reminders skip demo customers and reach real ones', function () {
    $tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $tenant->id);
    app()->instance('tenant', $tenant);
    User::factory()->for($tenant)->create(['email' => 'owner@gmail.com']);
    $agent = User::factory()->agent()->for($tenant)->create(['email' => 'tech@gmail.com']);
    $route = Route::forDay($tenant->id, $agent->id, $tenant->today()->addDay()->toDateString());
    foreach (['robert@example.test', 'jan@gmail.com'] as $email) {
        $customer = Customer::factory()->for($tenant)->create(['email' => $email]);
        RouteStop::factory()->for($route)->for(Pool::factory()->for($tenant)->for($customer)->create())->create(['status' => 'pending']);
    }

    app(DailyOpsChecks::class)->run($tenant->id);

    expect(delivered())->toContain('jan@gmail.com')->not->toContain('robert@example.test');
});
