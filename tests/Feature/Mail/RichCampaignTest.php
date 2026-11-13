<?php

declare(strict_types=1);

use App\Jobs\SendCampaignEmail;
use App\Mail\CampaignMail;
use App\Models\Customer;
use App\Models\MailCampaign;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmailHtml;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/*
 * Email people writes rich text: cleaned to an allow-list on the server, styled for mail
 * clients, sent with a plain-text copy, and {first name} filled in per recipient.
 */

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Blue Lagoon Pools']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('cleaning keeps the formatting and drops anything that could run or reach out', function () {
    $dirty = '<h2 onclick="x()">Spring</h2><p style="color:red">Hi <strong>you</strong> <em>and</em> <u>yours</u></p>'
        .'<script>alert(1)</script><ul><li>One</li></ul><img src="https://evil.test/p.png">'
        .'<a href="javascript:alert(1)">bad</a> <a href="https://good.test/x" target="_blank">good</a><iframe src="https://evil.test"></iframe>';

    $clean = EmailHtml::clean($dirty);

    expect($clean)
        ->toContain('<h2>Spring</h2>', '<strong>you</strong>', '<em>and</em>', '<u>yours</u>', '<li>One</li>', '<a href="https://good.test/x">good</a>')
        ->not->toContain('onclick', 'style=', '<script', 'alert(1)', '<img', 'javascript:', '<iframe', 'target=');
});

test('list items lose the editor\'s inner paragraphs, and trailing empty paragraphs go', function () {
    expect(EmailHtml::clean('<p>Hi</p><ul><li><p>Skimmer</p></li><li><p>Pump</p></li></ul><p></p><p> </p>'))
        ->toBe('<p>Hi</p><ul><li>Skimmer</li><li>Pump</li></ul>');
});

test('an editor left empty is no message', function () {
    expect(EmailHtml::hasText('<p></p><p> </p>'))->toBeFalse()
        ->and(EmailHtml::hasText('<p>Hi</p>'))->toBeTrue();

    $this->actingAs($this->admin)
        ->post('/people/email', ['audience' => 'customers', 'subject' => 'x', 'body' => '<p></p>'])
        ->assertInvalid(['body' => 'Write a message.']);
});

test('the plain-text copy keeps paragraphs and lists apart and spells links out', function () {
    $text = EmailHtml::toText('<p>Hi there,</p><ul><li>Skimmer</li><li>Pump</li></ul><p>See <a href="https://x.test">our site</a> &amp; more</p>');

    expect($text)->toBe("Hi there,\n\n- Skimmer\n- Pump\n\nSee our site (https://x.test) & more");
});

test('the plain-text copy numbers a numbered list, and ignores line breaks between tags', function () {
    $text = EmailHtml::toText("<p>Steps:</p>\n<ol>\n<li>Cover off</li>\n<li>Pump on</li>\n</ol>\n<ul>\n<li>Gate open</li>\n</ul>");

    expect($text)->toBe("Steps:\n\n1. Cover off\n2. Pump on\n\n- Gate open");
});

test('{first name} becomes each person\'s first name, escaped, or "there" without one', function () {
    expect(EmailHtml::personalise('<p>Hi {first name},</p>', 'Robert'))->toBe('<p>Hi Robert,</p>')
        ->and(EmailHtml::personalise('<p>Hi {First Name},</p>', '<b>Bo</b>'))->toBe('<p>Hi &lt;b&gt;Bo&lt;/b&gt;,</p>')
        ->and(EmailHtml::personalise('<p>Hi {first name},</p>', null))->toBe('<p>Hi there,</p>');
});

test('the saved message is the cleaned one, and each email gets its person\'s first name', function () {
    Queue::fake();
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'email' => 'r@x.test']);

    $this->actingAs($this->admin)->post('/people/email', [
        'audience' => 'customers',
        'subject' => 'Spring, {first name}',
        'body' => '<p>Hi {first name},</p><p>Open <strong>soon</strong><script>x()</script></p>',
    ])->assertRedirect();

    expect(MailCampaign::query()->latest('id')->value('body'))->toBe('<p>Hi {first name},</p><p>Open <strong>soon</strong></p>');
    Queue::assertPushed(SendCampaignEmail::class, fn (SendCampaignEmail $job) => $job->firstName === 'Robert');
});

test('the email opens as written: styled HTML, a plain-text part, and no automatic greeting', function () {
    $mail = new CampaignMail('Spring, {first name}', '<p>Hi {first name},</p><p>See <a href="https://x.test">this</a></p>', 'Robert Anderson', null, null, $this->tenant, null, 'Robert');

    $html = $mail->render();
    // The subject is the email's title too: the name goes in there as well, and no placeholder is left anywhere.
    expect($html)->toContain('<title>Spring, Robert</title>', '>Spring, Robert</h1>')->not->toContain('{first name}');
    expect($html)->toContain('<p style="margin: 0 0 12px;">Hi Robert,</p>', 'style="color: #0284c7; text-decoration: underline;" href="https://x.test"')
        ->not->toContain('Hi Robert Anderson,');

    $mail->assertHasSubject('Spring, Robert');
    Mail::fake();
    Mail::to('r@x.test')->send($mail);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $m) => $m->content()->text === 'emails.campaign-text');
    expect(view('emails.campaign-text', ['text' => EmailHtml::toText('<p>Hi Robert,</p>'), 'unsubscribeUrl' => 'https://x.test/u?a=1&b=2'])->render())
        ->toContain("Hi Robert,\n", 'Unsubscribe: https://x.test/u?a=1&b=2');
});

test('a message written before rich text keeps its line breaks', function () {
    $html = (new CampaignMail('Old', "Line one\nLine two <3", 'Sam', null))->render();

    expect($html)->toContain('Line one<br />', 'Line two &lt;3');
});
