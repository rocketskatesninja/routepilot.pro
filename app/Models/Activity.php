<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'model_type',
        'model_id',
        'ip_address',
        'user_agent',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity.
     *
     * @param string $action The action performed (create, update, delete, etc.)
     * @param string $description Description of the activity
     * @param User $user The user who performed the action
     * @param Model|null $model The model that was affected (optional)
     * @return Activity
     */
    public static function log(string $action, string $description, User $user, $model = null): Activity
    {
        $activityData = [
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($model) {
            $activityData['model_type'] = get_class($model);
            $activityData['model_id'] = $model->id;
        }

        return static::create($activityData);
    }
} 