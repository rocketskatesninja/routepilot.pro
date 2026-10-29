<?php

declare(strict_types=1);

use App\Actions\GenerateInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/*
 * Invoice numbers say whose bill it is and when: the customer's last name and
 * the invoice date on the company's calendar ("RODRIGUEZ-20261101"); another
 * one with the same name that day gets "-2", "-3".
 */

beforeEach(function () {
    Carbon::setTestNow('2026-11-01 03:00:00'); // still Oct 31 in New York
    $this->tenant = Tenant::factory()->create(['timezone' => 'America/New_York']);
    app()->instance('tenant_id', $this->tenant->id);
});

/** Bill a customer for one charge; the invoice's number. */
function billedNumber(Customer $customer): string
{
    manualCharge($customer, 50, 'Weekly service');

    return (string) app(GenerateInvoice::class)->handle($customer)?->number;
}

test('the number is the last name and the company’s date', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['first_name' => 'Diane', 'last_name' => 'Rodríguez']);

    expect(billedNumber($customer))->toBe('RODRIGUEZ-20261031');
});

test('another invoice that day, for them or a namesake, gets the next suffix', function () {
    $smith = Customer::factory()->for($this->tenant)->create(['last_name' => 'Smith']);
    $otherSmith = Customer::factory()->for($this->tenant)->create(['last_name' => 'Smith']);

    expect([billedNumber($smith), billedNumber($otherSmith), billedNumber($smith)])
        ->toBe(['SMITH-20261031', 'SMITH-20261031-2', 'SMITH-20261031-3']);
});

test('odd names are cleaned up, and a missing last name falls back to the first', function () {
    $hyphen = Customer::factory()->for($this->tenant)->create(['last_name' => "O'Brien-Smythe Van Der Berghe"]);
    $noLast = Customer::factory()->for($this->tenant)->create(['first_name' => 'Cher', 'last_name' => null]);
    $nothing = Customer::factory()->for($this->tenant)->create(['first_name' => '李', 'last_name' => '—']);

    expect(billedNumber($hyphen))->toBe('OBRIENSMYTHEVANDERBE-20261031')
        ->and(billedNumber($noLast))->toBe('CHER-20261031')
        ->and(billedNumber($nothing))->toBe('INVOICE-20261031');
});

test('old numbers stay as they were, and other companies’ numbers don’t count', function () {
    $customer = Customer::factory()->for($this->tenant)->create(['last_name' => 'Park']);
    $old = Invoice::create(['customer_id' => $customer->id, 'number' => 'INV-00007', 'status' => 'sent', 'subtotal' => 50, 'tax' => 0, 'total' => 50, 'amount_paid' => 0]);

    $other = Tenant::factory()->create();
    Invoice::withoutGlobalScopes()->forceCreate([
        'tenant_id' => $other->id, 'customer_id' => Customer::factory()->for($other)->create()->id,
        'number' => 'PARK-20261031', 'status' => 'sent', 'subtotal' => 1, 'tax' => 0, 'total' => 1, 'amount_paid' => 0,
    ]);

    expect(billedNumber($customer))->toBe('PARK-20261031')
        ->and($old->fresh()?->number)->toBe('INV-00007');
});
