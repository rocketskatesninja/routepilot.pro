<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Customer;
use App\Models\Pool;
use App\Services\PhotoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Create a customer and, optionally, their first pool + service location in
 * one transaction (the "add customer + pool" flow). tenant_id is auto-filled
 * by BelongsToTenant from the bound tenant.
 */
class CreateCustomer
{
    public function __construct(private readonly PhotoService $photos) {}

    /**
     * @param  array<string, mixed>  $data  validated customer (+ optional pool) fields
     */
    public function handle(array $data): Customer
    {
        $customer = DB::transaction(function () use ($data): Customer {
            $customer = Customer::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address_line1' => $data['address_line1'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'zip' => $data['zip'] ?? null,
                'notes' => $data['notes'] ?? null,
                'bill_chemicals' => $data['bill_chemicals'] ?? false,
            ]);

            if (! empty($data['pool_name'])) {
                $pool = Pool::create([
                    'customer_id' => $customer->id,
                    'name' => $data['pool_name'],
                    'type' => $data['pool_type'] ?? 'inground',
                    'volume_gallons' => $data['pool_volume'] ?? null,
                    'sanitizer_type' => $data['pool_sanitizer'] ?? 'chlorine',
                ]);

                $pool->serviceLocation()->create([
                    'address_line1' => $data['address_line1'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'zip' => $data['zip'] ?? null,
                ]);
            }

            return $customer;
        });

        $photo = $data['photo'] ?? null;
        if ($photo instanceof UploadedFile) {
            $customer->forceFill(['photo_path' => $this->photos->store($photo, 'customers')])->save();
        }

        return $customer;
    }
}
