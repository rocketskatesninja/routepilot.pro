<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A route's stops changed (optimized, rearranged, skipped, or a visit
 * completed). Broadcast on the tenant's private channel so the back-office
 * schedule board live-refreshes the affected day without a manual reload.
 */
class RouteUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public string $date,
        public ?int $agentId = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.$this->tenantId);
    }

    public function broadcastAs(): string
    {
        return 'RouteUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['date' => $this->date, 'agent_id' => $this->agentId];
    }
}
