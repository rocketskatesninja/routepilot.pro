<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CampaignRecipient — one address on a MailCampaign and its delivery outcome
 * (queued → sent | failed). Not directly tenant-scoped; it's always reached
 * through its (tenant-scoped) campaign, and the queue worker updates it by id.
 *
 * @property int $mail_campaign_id
 * @property string $email
 * @property string|null $name
 * @property string $status
 * @property string|null $error
 */
class CampaignRecipient extends Model
{
    /** @var list<string> */
    protected $fillable = ['mail_campaign_id', 'email', 'name', 'status', 'error'];

    /** @return BelongsTo<MailCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'mail_campaign_id');
    }
}
