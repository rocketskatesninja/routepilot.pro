<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Charge;
use App\Models\ChatSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\PaymentMethod;
use App\Models\Pool;
use App\Models\ServiceLocation;
use App\Models\ServiceRequest;
use App\Models\ServiceVisit;
use App\Models\User;
use App\Models\VisitPhoto;
use App\Services\PhotoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remove an archived customer's personal details on request — run by hand,
 * never on a schedule. The business records stay so the books still add up:
 * invoices, payments, charges and visits (with their water readings), all now
 * under "Former customer #id".
 *
 * Removed: name, email, phone, address, notes and security code; every photo
 * (theirs, their pools', their service addresses' and their visits'); pool
 * names and notes (pools become "Pool 1", "Pool 2"…), service addresses, gate
 * codes and access notes; visit notes (both kinds) and GPS points; request messages and
 * replies; invoice notes; saved cards and the Stripe link; the website leads
 * they came from; their address in email-campaign logs; and their portal login
 * with its AI conversations, notifications and sessions. Charge descriptions
 * that named a pool are rewritten to the new pool name. Irreversible.
 */
class AnonymizeCustomer
{
    public function __construct(private PhotoService $photos) {}

    /** @return bool false when the customer isn't archived, or was already done */
    public function handle(Customer $customer): bool
    {
        if (! $customer->trashed() || $customer->getAttribute('anonymized_at') !== null) {
            return false;
        }

        $files = [];
        DB::transaction(function () use ($customer, &$files): void {
            $id = (int) $customer->id;
            $email = $customer->email;
            $files[] = $customer->getAttribute('photo_path');

            // Pools: anonymous names, and the old names rewritten out of the charges that quoted them.
            $pools = Pool::withTrashed()->where('customer_id', $id)->orderBy('id')->get();
            $renames = [];
            foreach ($pools->values() as $i => $pool) {
                $label = $pools->count() === 1 ? 'Pool' : 'Pool '.($i + 1);
                if ($pool->name !== '') {
                    $renames[$pool->name] = $label;
                }
                $files[] = $pool->getAttribute('photo_path');
                $pool->forceFill(['name' => $label, 'notes' => null, 'photo_path' => null])->saveQuietly();
            }
            uksort($renames, fn (string $a, string $b): int => strlen($b) <=> strlen($a)); // longest first
            foreach (Charge::query()->where('customer_id', $id)->get(['id', 'description']) as $charge) {
                $description = strtr((string) $charge->description, $renames);
                if ($description !== $charge->description) {
                    Charge::query()->whereKey($charge->id)->update(['description' => $description]);
                }
            }

            $poolIds = $pools->pluck('id');
            foreach (ServiceLocation::query()->whereIn('pool_id', $poolIds)->get() as $location) {
                $files[] = $location->getAttribute('photo_path');
                $location->forceFill([
                    'address_line1' => null, 'address_line2' => null, 'city' => null, 'state' => null, 'zip' => null,
                    'lat' => null, 'lng' => null, 'access_notes' => null, 'gate_code' => null, 'photo_path' => null,
                ])->saveQuietly();
            }

            $visitIds = ServiceVisit::query()->whereIn('pool_id', $poolIds)->pluck('id');
            $photos = VisitPhoto::query()->whereIn('service_visit_id', $visitIds);
            array_push($files, ...$photos->pluck('photo_path')->all());
            $photos->delete();
            ServiceVisit::query()->whereKey($visitIds)->update(['notes' => null, 'office_notes' => null, 'completed_lat' => null, 'completed_lng' => null]);

            ServiceRequest::query()->where('customer_id', $id)->update(['message' => null, 'reply' => null]);
            Invoice::query()->where('customer_id', $id)->update(['notes' => null]);
            PaymentMethod::query()->where('customer_id', $id)->delete();
            Lead::query()->where('customer_id', $id)->delete();

            // Their address in email-campaign delivery logs (this company's campaigns only).
            if (is_string($email) && $email !== '') {
                DB::table('campaign_recipients')
                    ->whereIn('mail_campaign_id', DB::table('mail_campaigns')->where('tenant_id', $customer->getAttribute('tenant_id'))->select('id'))
                    ->where('email', $email)
                    ->update(['email' => "former-customer-{$id}@deleted.invalid", 'name' => null]);
            }

            $login = $customer->user_id !== null ? User::withTrashed()->find($customer->user_id) : null;
            if ($login !== null) {
                $files[] = $login->getAttribute('avatar_path');
                $this->forgetLogin($login, $id);
            }

            $customer->forceFill([
                'first_name' => 'Former', 'last_name' => "customer #{$id}",
                'email' => null, 'phone' => null,
                'address_line1' => null, 'address_line2' => null, 'city' => null, 'state' => null, 'zip' => null,
                'lat' => null, 'lng' => null, 'notes' => null, 'admin_notes' => null, 'security_code' => null,
                'photo_path' => null, 'user_id' => null,
                'stripe_customer_id' => null, 'default_payment_method_id' => null, 'autopay_enabled' => false,
                'anonymized_at' => now(),
            ])->saveQuietly();
        });

        foreach ($files as $path) {
            $this->photos->delete(is_string($path) && $path !== '' ? $path : null);
        }

        return true;
    }

    /** Their portal login: AI conversations, notifications and sessions gone, the account itself emptied and closed. */
    private function forgetLogin(User $login, int $customerId): void
    {
        ChatSession::query()->where('user_id', $login->id)->delete(); // messages cascade
        DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $login->id)->delete();
        DB::table('sessions')->where('user_id', $login->id)->delete();

        $login->forceFill([
            'first_name' => 'Former', 'last_name' => "customer #{$customerId}",
            'email' => "former-customer-{$customerId}-".Str::lower(Str::random(8)).'@deleted.invalid',
            'phone' => null, 'avatar_path' => null, 'admin_notes' => null, 'google_id' => null,
            'password' => Str::random(64),
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
            'remember_token' => Str::random(60), 'session_token' => Str::random(40),
            'is_active' => false,
        ])->saveQuietly();
        if (! $login->trashed()) {
            $login->delete();
        }
    }
}
