<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * The company signature: a default from the company's details until it writes its own
 * on Company settings, added to Email people messages unless switched off.
 */

beforeEach(function () {
    Queue::fake();
    $this->tenant = Tenant::factory()->create(['name' => 'Blue Lagoon Pools', 'reply_to_email' => 'office@bluelagoon.test']);
    $this->tenant->forceFill(['address_line1' => '12 Ocean Dr', 'city' => 'Tybee', 'state' => 'GA', 'postal_code' => '31328'])->save();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    Customer::factory()->for($this->tenant)->create(['email' => 'r@x.test']);
});

function companyForm(array $changes = []): array
{
    $t = test()->tenant->fresh();

    return [
        'name' => $t->name, 'timezone' => 'America/New_York', 'brand_color' => '#0ea5e9', 'tax_rate_percent' => 0,
        'reply_to_email' => $t->reply_to_email, 'address_line1' => $t->address_line1, 'city' => $t->city,
        'state' => $t->state, 'postal_code' => $t->postal_code, ...$changes,
    ];
}

test('until the company writes one, the signature is made from its details', function () {
    expect($this->tenant->emailSignature())->toStartWith('<p>Thanks,<br><strong>Blue Lagoon Pools</strong><br>12 Ocean Dr')
        ->toContain('office@bluelagoon.test');
});

test('Company settings saves the company\'s own signature, cleaned; emptied, it goes back to the default', function () {
    $this->actingAs($this->admin)->patch('/company', companyForm([
        'email_signature' => '<p>Cheers,<br><strong>Marcus</strong> <script>x()</script><a href="javascript:x()">x</a></p>',
    ]))->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->email_signature)->toBe('<p>Cheers,<br /><strong>Marcus</strong> <a>x</a></p>')
        ->and($this->tenant->fresh()->emailSignature())->toBe('<p>Cheers,<br /><strong>Marcus</strong> <a>x</a></p>');

    $this->actingAs($this->admin)->get('/company')
        ->assertInertia(fn (Assert $page) => $page->where('company.email_signature', '<p>Cheers,<br /><strong>Marcus</strong> <a>x</a></p>'));

    $this->actingAs($this->admin)->patch('/company', companyForm(['email_signature' => '<p></p>']))->assertSessionHasNoErrors();
    expect($this->tenant->fresh()->email_signature)->toBeNull()
        ->and($this->tenant->fresh()->emailSignature())->toContain('Blue Lagoon Pools');
});

test('the email form offers the signature, and it goes on the end of the message unless left off', function () {
    $this->tenant->forceFill(['email_signature' => '<p>Cheers, Marcus</p>'])->save();

    $this->actingAs($this->admin)->get('/people')
        ->assertInertia(fn (Assert $page) => $page->where('signature', '<p>Cheers, Marcus</p>'));

    $this->post('/people/email', ['audience' => 'customers', 'subject' => 'A', 'body' => '<p>Hello</p>', 'signature' => true]);
    $this->post('/people/email', ['audience' => 'customers', 'subject' => 'B', 'body' => '<p>Hello</p>', 'signature' => false]);

    expect(MailCampaign::query()->where('subject', 'A')->value('body'))->toBe('<p>Hello</p><p>Cheers, Marcus</p>')
        ->and(MailCampaign::query()->where('subject', 'B')->value('body'))->toBe('<p>Hello</p>');
});

test('each company gets only its own signature', function () {
    $other = Tenant::factory()->create(['name' => 'Rival Pools']);
    $other->forceFill(['email_signature' => '<p>Rival sig</p>'])->save();

    $this->actingAs($this->admin)->post('/people/email', ['audience' => 'customers', 'subject' => 'C', 'body' => '<p>Hi</p>', 'signature' => true]);

    expect(MailCampaign::query()->where('subject', 'C')->value('body'))->not->toContain('Rival');
});
