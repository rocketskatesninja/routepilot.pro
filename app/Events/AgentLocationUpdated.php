<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An agent moved. Broadcast immediately (not queued — location is real-time and
 * ephemeral) on the tenant's private channel so the dispatch map slides the
 * agent's marker live.
 */
class AgentLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $tenantId,
        public int $agentId,
        public float $lat,
        public float $lng,
        public ?int $heading,
        public string $recordedAt,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('tenant.'.$this->tenantId);
    }

    public function broadcastAs(): string
    {
        return 'AgentLocationUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'agent_id' => $this->agentId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'heading' => $this->heading,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
