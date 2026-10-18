<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;

/**
 * Exporting the People list: the same rows, in the same order, as the list on
 * screen (tab, search and sort), every page of it — and only for admins.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant_id', $this->tenant->id);
    $this->admin = User::factory()->for($this->tenant)->create();
    $this->agent = User::factory()->agent()->for($this->tenant)->create(['first_name' => 'Marcus', 'last_name' => 'Bennett']);
});

/** The CSV's rows (header row dropped), each as a list of cells. */
function exportedCsv(string $query): array
{
    $csv = test()->actingAs(test()->admin)->get('/people/export?format=csv&'.$query)->assertOk()->streamedContent();
    $lines = array_map(str_getcsv(...), array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));

    return array_slice($lines, 1);
}

test('the CSV is the list on screen: same tab, search and sort, every page', function () {
    foreach (['Anderson', 'Andrews', 'Baker'] as $last) {
        Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'last_name' => $last]);
    }
    Customer::factory()->for($this->tenant)->count(30)->create(['first_name' => 'Zed', 'last_name' => 'Smith']); // more than one page

    $rows = exportedCsv('type=customers&search=and&sort=name&dir=desc');

    expect(array_column($rows, 0))->toBe(['Robert Andrews', 'Robert Anderson'])
        ->and(count(exportedCsv('type=customers')))->toBe(33)
        ->and(array_column(exportedCsv('type=agents'), 0))->toBe(['Marcus Bennett']);
});

test('each customer row carries contact details, address and balance', function () {
    $customer = Customer::factory()->for($this->tenant)->create([
        'first_name' => 'Robert', 'last_name' => 'Anderson', 'email' => 'robert@anderson.test', 'phone' => '555-0100',
        'address_line1' => '12 Palm Way', 'city' => 'Tampa', 'state' => 'FL', 'zip' => '33601',
    ]);
    manualCharge($customer, 45);

    [$row] = exportedCsv('type=customers');

    expect($row)->toBe(['Robert Anderson', 'Customer', 'robert@anderson.test', '555-0100', '12 Palm Way, Tampa, FL 33601', 'No', '', '45.00']);
});

test('a name that looks like a spreadsheet formula is kept as text', function () {
    Customer::factory()->for($this->tenant)->create(['first_name' => '=HYPERLINK("http://x")', 'last_name' => '']);

    [$row] = exportedCsv('type=customers');

    expect($row[0])->toStartWith("'=");
});

test('the archived tab exports archived customers only', function () {
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Still', 'last_name' => 'Here']);
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Gone', 'last_name' => 'Away'])->delete();

    expect(array_column(exportedCsv('type=archived'), 0))->toBe(['Gone Away']);
});

test('another company\'s people are never in the export', function () {
    $other = Tenant::factory()->create();
    Customer::factory()->for($other)->create(['first_name' => 'Their', 'last_name' => 'Customer']);
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Our', 'last_name' => 'Customer']);

    expect(array_column(exportedCsv('type=customers'), 0))->toBe(['Our Customer']);
});

test('the PDF downloads, and every export is audited', function () {
    Customer::factory()->for($this->tenant)->create(['first_name' => 'Robert', 'last_name' => 'Anderson']);

    $this->actingAs($this->admin)->get('/people/export?format=pdf&type=customers&search=robert')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('customers-'.$this->tenant->today()->toDateString().'.pdf');

    $log = AuditLog::query()->where('action', 'people.exported')->sole();
    expect($log->changes)->toMatchArray(['format' => 'pdf', 'type' => 'customers', 'search' => 'robert', 'rows' => 1]);
});

test('agents can\'t export the client list', function () {
    $this->actingAs($this->agent)->get('/people/export?format=csv')->assertForbidden();
});
