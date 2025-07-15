<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'body',
        'default_signature',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Replace placeholders in template.
     */
    public function replacePlaceholders(array $data): string
    {
        $body = $this->body;
        
        foreach ($data as $key => $value) {
            $body = str_replace("{{" . $key . "}}", $value, $body);
        }
        
        return $body;
    }

    /**
     * Get subject with replaced placeholders.
     */
    public function getSubjectWithPlaceholders(array $data): string
    {
        $subject = $this->subject ?? '';
        
        foreach ($data as $key => $value) {
            $subject = str_replace("{{" . $key . "}}", $value, $subject);
        }
        
        return $subject;
    }
} 