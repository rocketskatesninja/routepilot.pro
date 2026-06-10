<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
});

test('an admin exports invoices as CSV', function () {
    $customer = Customer::factory()->for($this->tenant)->create();
    Invoice::create([
        'customer_id' => $customer->id, 'number' => 'INV-00001', 'status' => 'sent',
        'subtotal' => 50, 'tax' => 0, 'total' => 50, 'amount_paid' => 0, 'issued_at' => now(), 'due_at' => now()->addDays(30),
    ]);

    $this->actingAs($this->admin)
        ->get('/balances/export')
        ->assertOk()
        ->assertDownload('invoices.csv');
});

test('agents cannot export', function () {
    $agent = User::factory()->agent()->for($this->tenant)->create();

    $this->actingAs($agent)->get('/balances/export')->assertForbidden();
});
