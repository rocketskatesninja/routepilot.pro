<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * Get a setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Set a setting value by key.
     */
    public static function setValue(string $key, $value, string $type = 'string', string $group = 'general', string $description = null)
    {
        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );
        return $setting;
    }

    /**
     * Get all settings by group.
     */
    public static function getByGroup(string $group)
    {
        return static::where('group', $group)->get();
    }

    /**
     * Get settings as key-value pairs.
     */
    public static function getKeyValuePairs(string $group = null)
    {
        $query = static::query();
        if ($group) {
            $query->where('group', $group);
        }
        
        return $query->pluck('value', 'key')->toArray();
    }
} 