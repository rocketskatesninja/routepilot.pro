<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The live "on-my-way" window for the customer whose pool is the agent's next
 * pending stop. Broadcast immediately on the customer's private channel as the
 * agent moves, so the portal "Next visit" card updates in place.
 */
class VisitEtaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $customerId,
        public string $window,
        public ?string $pool,
        public string $date,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('customer.'.$this->customerId);
    }

    public function broadcastAs(): string
    {
        return 'VisitEtaUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['window' => $this->window, 'pool' => $this->pool, 'date' => $this->date];
    }
}
