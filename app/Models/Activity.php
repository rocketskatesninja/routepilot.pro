<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'description',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * Get the user for this activity.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related model.
     */
    public function subject()
    {
        return $this->morphTo('model');
    }

    /**
     * Log an activity.
     */
    public static function log(string $action, string $description, $user = null, $subject = null, array $properties = [])
    {
        return static::create([
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => $subject ? get_class($subject) : null,
            'model_id' => $subject?->id,
            'description' => $description,
            'properties' => $properties,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope for activities by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for activities by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for recent activities.
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
} 