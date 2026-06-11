<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app alert to tenant admins when a customer's autopay charge is declined.
 */
class AutopayDeclined extends Notification
{
    use Queueable;

    public function __construct(public Customer $customer, public float $amount) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ! $notifiable->wantsNotification('billing') ? [] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Autopay declined: '.$this->customer->displayName(),
            'body' => '$'.number_format($this->amount, 2).' could not be charged.',
            'url' => '/balances',
        ];
    }
}
