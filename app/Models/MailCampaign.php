<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * MailCampaign — a record of one mailing send: subject, audience, and the
 * delivery tallies fed back from the queue (recipient / sent / failed).
 * tenant_id is null for platform-wide (super-admin) sends.
 *
 * @property string $subject
 * @property string $audience
 * @property int $recipient_count
 * @property int $sent_count
 * @property int $failed_count
 * @property Carbon|null $sent_at
 * @property list<array{name: string, size: int, mime: string, path: string|null}>|null $attachments path null once cleared (DataRetention)
 */
class MailCampaign extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'created_by', 'subject', 'body', 'attachments', 'audience',
        'recipient_count', 'sent_count', 'failed_count', 'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'attachments' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }
}
