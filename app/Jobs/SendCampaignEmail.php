<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CampaignMail;
use App\Models\CampaignRecipient;
use App\Models\MailCampaign;
use App\Services\TenantMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

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
        public ?int $recipientId = null,
    ) {}

    public function handle(TenantMailer $tenantMailer): void
    {
        $prepared = $tenantMailer->prepare($this->tenantId);

        Mail::mailer($prepared['mailer'])
            ->to($this->email)
            ->send(new CampaignMail($this->subject, $this->body, $this->recipientName, $this->unsubscribeUrl, $prepared['from']));

        $this->mark('sent', null);
    }

    /** All retries exhausted — record the delivery as failed with the reason. */
    public function failed(Throwable $e): void
    {
        $this->mark('failed', Str::limit($e->getMessage(), 240));
    }

    /**
     * Flip this recipient's delivery row and tally it on the campaign (atomic
     * increment, race-safe across concurrent workers). Runs in the queue worker
     * where the tenant scope is unbound, so we reach the rows by id.
     */
    private function mark(string $status, ?string $error): void
    {
        if ($this->recipientId === null) {
            return;
        }

        $recipient = CampaignRecipient::query()->find($this->recipientId);
        if ($recipient === null || $recipient->status === $status) {
            return;
        }

        $recipient->update(['status' => $status, 'error' => $error]);
        MailCampaign::withoutGlobalScopes()->whereKey($recipient->mail_campaign_id)
            ->increment($status === 'sent' ? 'sent_count' : 'failed_count');
    }
}
