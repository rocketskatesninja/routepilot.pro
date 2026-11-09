<?php

/*
 * The throwaway company the click test (scripts/click-test.mjs) runs against:
 * made fresh for each run, deleted afterwards. It's run on staging through tinker
 * with CLICK_ACTION=setup or teardown, and refuses to run anywhere else.
 */

use App\Actions\CompleteVisit;
use App\Actions\GenerateInvoice;
use App\Actions\RegisterTenant;
use App\Models\ChemicalInventory;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Pool;
use App\Models\Route;
use App\Models\RouteStop;
use App\Models\ServiceRequest;
use App\Models\ServiceSubscription;
use App\Models\ServiceType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;

if (! str_ends_with((string) parse_url((string) config('app.url'), PHP_URL_HOST), '.local')) {
    throw new RuntimeException('The click test only runs on staging.');
}

$company = 'Click Test Pools';
$slug = 'click-test-pools';
$superEmail = 'click-platform@routepilot.test';

$teardown = function () use ($company, $slug, $superEmail): void {
    $tenant = Tenant::withTrashed()->where('slug', $slug)->where('name', $company)->first();
    if ($tenant !== null) {
        $ids = User::withTrashed()->where('tenant_id', $tenant->id)->pluck('id');
        DB::table('notifications')->where('notifiable_type', User::class)->whereIn('notifiable_id', $ids)->delete();
        $tenant->forceDelete();
        User::withTrashed()->whereKey($ids)->get()->each->forceDelete();
    }
    User::withTrashed()->where('email', $superEmail)->get()->each->forceDelete();
};

$teardown();

if (getenv('CLICK_ACTION') !== 'setup') {
    echo 'CLICK_JSON={"removed":true}'.PHP_EOL;

    return;
}

$password = bin2hex(random_bytes(12));

$account = function (string $email, string $role, ?int $tenantId, string $first, string $last) use ($password): User {
    $user = new User;
    $user->fill(['first_name' => $first, 'last_name' => $last, 'email' => $email, 'password' => $password]);
    $user->forceFill(['tenant_id' => $tenantId, 'role' => $role, 'email_verified_at' => now(), 'is_active' => true])->save();

    return $user;
};

$office = app(RegisterTenant::class)(['company' => $company, 'first_name' => 'Click', 'last_name' => 'Owner', 'email' => 'click-office@routepilot.test', 'password' => $password]);
$office->forceFill(['email_verified_at' => now()])->save();
$tenant = $office->tenant;
app()->instance('tenant_id', $tenant->id);
app()->instance('tenant', $tenant);

$tech = $account('click-tech@routepilot.test', 'agent', $tenant->id, 'Click', 'Tech');
$portal = $account('click-customer@routepilot.test', 'customer', $tenant->id, 'Casey', 'Customer');
$account($superEmail, 'super_admin', null, 'Click', 'Platform');

$service = ServiceType::factory()->for($tenant)->create(['name' => 'Weekly Pool Service', 'price' => 50, 'tasks' => ['Skim surface', 'Brush walls']]);
ServiceType::factory()->for($tenant)->create(['name' => 'Filter Clean', 'price' => 95, 'frequency' => 'one_time']);

ChemicalInventory::query()->chemicals()->get()->each(fn (ChemicalInventory $c) => $c->forceFill(['current_stock' => 20, 'reorder_threshold' => 5, 'sell_price' => 5])->save());
foreach ([['Pump lid O-ring', 'OR-112'], ['Skimmer basket', 'SK-9'], ['Pressure gauge', null]] as [$name, $number]) {
    ChemicalInventory::factory()->for($tenant)->create(['chemical_name' => $name, 'kind' => 'part', 'part_number' => $number, 'unit' => 'unit', 'current_stock' => 6, 'sell_price' => 12]);
}

$pools = [];
$customers = [];
foreach (['Ana Reyes', 'Ben Cole', 'Casey Customer', 'Dee Hart', 'Eli Moss', 'Fay Lund'] as $i => $name) {
    [$first, $last] = explode(' ', $name);
    $customers[$i] = Customer::factory()->for($tenant)->create(['first_name' => $first, 'last_name' => $last, 'email' => strtolower($first).'@example.test', 'user_id' => $name === 'Casey Customer' ? $portal->id : null]);
    $pools[$i] = Pool::factory()->for($tenant)->for($customers[$i])->create(['name' => 'Click Pool '.($i + 1)]);
    ServiceSubscription::factory()->for($tenant)->for($pools[$i])->for($service)->create(['assigned_agent_id' => $tech->id, 'status' => 'active']);
}
$plan = fn (int $i) => ServiceSubscription::query()->where('pool_id', $pools[$i]->id)->value('id');

$today = Route::forDay($tenant->id, $tech->id, $tenant->today()->toDateString());
$tomorrow = Route::forDay($tenant->id, $tech->id, $tenant->today()->addDay()->toDateString());
$stop = fn (Route $route, int $i) => RouteStop::factory()->for($route)->for($pools[$i])->create(['service_subscription_id' => $plan($i), 'status' => 'pending', 'stop_order' => $i + 1]);

$acid = ChemicalInventory::query()->where('standard_key', 'muriatic_acid')->sole();
$oring = ChemicalInventory::query()->where('part_number', 'OR-112')->sole();
foreach ([0, 1, 2] as $i) {
    app(CompleteVisit::class)->handle($stop($today, $i), [
        'ph' => 7.4, 'free_chlorine' => 3, 'alkalinity' => 90, 'notes' => 'All clean.',
        'tasks' => [['name' => 'Skim surface', 'done' => true], ['name' => 'Brush walls', 'done' => $i !== 1]],
        'treatments' => [['chemical_id' => $acid->id, 'amount' => 1]],
        'parts' => $i === 0 ? [['part_id' => $oring->id, 'amount' => 1]] : [],
    ], $tech);
}
foreach ([3, 4, 5] as $i) {
    $stop($today, $i);
    $stop($tomorrow, $i);
}

app(GenerateInvoice::class)->handle($customers[0]);

WorkOrder::factory()->for($tenant)->create(['pool_id' => $pools[0]->id, 'title' => 'Replace the skimmer lid', 'urgent' => true]);
WorkOrder::factory()->for($tenant)->create(['pool_id' => $pools[1]->id, 'title' => 'Check the heater', 'status' => 'done']);

$lead = new Lead(['name' => 'Gia Lane', 'email' => 'gia@example.test', 'phone' => '912-555-0100', 'message' => 'A quote for weekly service, please.', 'source' => 'website']);
$lead->forceFill(['tenant_id' => $tenant->id, 'status' => 'new'])->save();
$request = new ServiceRequest(['customer_id' => $customers[2]->id, 'pool_id' => $pools[2]->id, 'service_type_id' => $service->id, 'type' => 'service', 'message' => 'Could you add a filter clean?']);
$request->forceFill(['tenant_id' => $tenant->id, 'status' => 'pending'])->save();

echo 'CLICK_JSON='.json_encode([
    'slug' => $slug,
    'password' => $password,
    'accounts' => ['office' => 'click-office@routepilot.test', 'tech' => 'click-tech@routepilot.test', 'customer' => 'click-customer@routepilot.test', 'platform' => $superEmail],
    'nextPool' => 'Click Pool 4',
]).PHP_EOL;
