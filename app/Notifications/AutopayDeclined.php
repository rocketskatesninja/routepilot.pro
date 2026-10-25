<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;

/**
 * In-app alert to tenant admins when a customer's autopay charge is declined.
 */
class AutopayDeclined extends InAppNotification
{
    public function __construct(public Customer $customer, public float $amount) {}

    public function type(): string
    {
        return 'autopay_declined';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Autopay declined: '.$this->customer->displayName(),
            'body' => '$'.number_format($this->amount, 2).' could not be charged.',
            'url' => '/balances?selected='.$this->customer->id, // opens them, and lets AnonymizeCustomer find it
        ];
    }
}
