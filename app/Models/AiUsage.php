<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant's AI usage for one month (period = YYYY-MM). Tenant-scoped.
 *
 * @property int $messages
 */
class AiUsage extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_usage';

    /** @var list<string> */
    protected $fillable = ['period', 'messages', 'tokens', 'cost'];
}
