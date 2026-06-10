<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MailCampaign — a record of one mailing send: subject, audience, and the
 * delivery tallies fed back from the queue (recipient / sent / failed).
 * tenant_id is null for platform-wide (super-admin) sends.
 */
class MailCampaign extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'created_by', 'subject', 'body', 'audience',
        'recipient_count', 'sent_count', 'failed_count', 'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
