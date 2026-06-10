<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Services\TenantMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Deliver one campaign email through the sender tenant's own mailer. The job
 * carries no credentials — TenantMailer re-reads + decrypts the tenant's SMTP
 * config at run time.
 */
class SendCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public string $email,
        public string $subject,
        public string $body,
        public string $recipientName,
        public ?string $unsubscribeUrl,
        public ?int $tenantId,
    ) {}

    public function handle(TenantMailer $tenantMailer): void
    {
        $prepared = $tenantMailer->prepare($this->tenantId);

        Mail::mailer($prepared['mailer'])
            ->to($this->email)
            ->send(new CampaignMail($this->subject, $this->body, $this->recipientName, $this->unsubscribeUrl, $prepared['from']));
    }
}
