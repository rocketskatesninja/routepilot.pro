<?php

declare(strict_types=1);

use App\Mail\CampaignMail;
use App\Mail\EmailChangeVerificationMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentReceiptMail;
use App\Mail\ServiceReminderMail;
use App\Mail\ServiceRequestAnsweredMail;
use App\Mail\VisitRecapMail;
use App\Models\Customer;
use App\Models\Pool;
use App\Models\ServiceVisit;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EmailBrand;
use App\Support\WaterReport;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Blue Lagoon Pools', 'brand_color' => '#7c3aed']);
    app()->instance('tenant_id', $this->tenant->id);
    $this->customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert']);
    $this->pool = Pool::factory()->for($this->tenant)->for($this->customer)->create([
        'name' => 'Backyard Pool', 'custom_target_ranges' => ['ph' => ['min' => 7.4, 'max' => 7.8]],
    ]);
});

test('the water report reads like the portal, against the pool\'s own targets', function () {
    $visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'status' => 'completed', 'agent_id' => User::factory()->agent()->for($this->tenant)->create()->id,
    ]);
    $reading = $visit->chemicalReading()->create(['free_chlorine' => 3.4, 'ph' => 7.3, 'alkalinity' => 100]);

    $checks = WaterReport::checks($reading, $this->pool);

    expect(array_column($checks, 'name'))->toBe(['Chlorine', 'pH', 'Alkalinity'])
        ->and($checks[0])->toMatchArray(['status' => 'high', 'label' => 'A little high', 'reads' => '3.4 ppm', 'range' => '1–3 ppm'])
        ->and($checks[1])->toMatchArray(['status' => 'low', 'range' => '7.4–7.8']) // the pool's own pH target
        ->and($checks[2]['status'])->toBe('good')
        ->and(WaterReport::summary($checks))->toBe('Chlorine was a little high and pH was a little low. Your technician adjusts this as part of each visit.')
        ->and(WaterReport::summary($checks, false))->toBe('Chlorine was a little high and pH was a little low.');
});

test('the visit email is the visit report: the water bars, the tech\'s note, in the company\'s colour', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Ashley']);
    $visit = ServiceVisit::factory()->for($this->tenant)->for($this->pool)->create([
        'status' => 'completed', 'completed_at' => '2026-10-15 17:30:00', 'agent_id' => $agent->id, 'notes' => 'Cover is back on.',
    ]);
    $visit->chemicalReading()->create(['free_chlorine' => 2.6, 'ph' => 7.5]);
    $visit->tasks()->create(['task_name' => 'Brush walls', 'is_completed' => true]);

    $html = (new VisitRecapMail($visit, 50.0, 'https://example.test/pay'))->render();

    expect($html)->toContain('Hi Robert,')
        ->toContain('Your pool was serviced on')
        ->toContain('Brush walls')
        ->toContain('Ashley wrote')
        ->toContain('Cover is back on.')
        ->toContain('Healthy: 1–3 ppm')
        ->toContain('Everything was in the healthy range.')
        ->toContain('Pay $50.00')
        ->toContain('#7c3aed'); // the company's own colour, not a fixed blue
});

test('every email renders in the shared layout with the company\'s name', function () {
    $mails = [
        new ServiceReminderMail('Robert', 'Blue Lagoon Pools', 'Backyard Pool', 'Monday, October 19', 'Marcus', '9:00 – 10:00 AM', $this->tenant),
        new PaymentReceiptMail('Robert', 40.0, 'Blue Lagoon Pools', 'Oct 2, 2026', $this->tenant),
        new PaymentFailedMail('Robert', 40.0, 'Blue Lagoon Pools', 'https://example.test/pay', $this->tenant),
        new EmailChangeVerificationMail('Robert', 'new@example.test', 'https://example.test/confirm', $this->tenant),
        new ServiceRequestAnsweredMail('Robert', 'Blue Lagoon Pools', 'You’re scheduled', 'Weekly service starts Monday.', 'See you then!', false, null, $this->tenant),
        new CampaignMail('Spring opening', 'We open pools in March.', 'Robert', 'https://example.test/unsub', null, $this->tenant),
    ];

    foreach ($mails as $mail) {
        expect($mail->render())->toContain('Blue Lagoon Pools')->toContain('Hi Robert,');
    }
});

test('an email without a company wears RoutePilot\'s own look, and button text stays readable on light colours', function () {
    expect(EmailBrand::for(null)['name'])->toBe('RoutePilot')
        ->and(EmailBrand::for(Tenant::factory()->make(['brand_color' => '#fde047']))['on_color'])->toBe('#111827') // light yellow → dark text
        ->and(EmailBrand::for(Tenant::factory()->make(['brand_color' => '#1e3a8a']))['on_color'])->toBe('#ffffff'); // navy → white text
});
