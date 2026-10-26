<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Show one notification on each of a user's phones (web push). Queued, so a
 * slow push service never holds up the request that raised it. A phone the
 * push service says is gone (notifications turned off, browser uninstalled,
 * logged out) is forgotten.
 */
class SendWebPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @param  array{title: string, body: string, url: string, tag: string, icon?: string|null}  $message */
    public function __construct(public int $userId, public array $message) {}

    public function handle(WebPush $push): void
    {
        $payload = json_encode($this->message, JSON_THROW_ON_ERROR);
        foreach (PushSubscription::query()->where('user_id', $this->userId)->get() as $phone) {
            $push->queueNotification(new Subscription($phone->endpoint, $phone->public_key, $phone->auth_token, 'aes128gcm'), $payload);
        }

        foreach ($push->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
            } elseif (! $report->isSuccess()) {
                Log::warning('Web push failed', ['user_id' => $this->userId, 'reason' => $report->getReason()]);
            }
        }
    }
}
