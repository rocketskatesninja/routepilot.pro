<?php

declare(strict_types=1);

namespace App\Services\Chat\Tools;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadSubmitted;
use App\Services\Chat\AiTool;
use Illuminate\Support\Facades\Notification;

/**
 * The public chatbot's one tool: capture a visitor's contact details as a lead
 * (source `chatbot`) and notify the tenant's admins. NOT registered in the
 * authed ToolRegistry — it's offered only to the public lead-capture assistant.
 */
class CaptureLeadTool extends AiTool
{
    public function name(): string
    {
        return 'capture_lead';
    }

    public function description(): string
    {
        return 'Save the visitor as a lead so the team can follow up. Call this ONCE you have their name '
            .'and at least an email or phone, and they want a quote, a booking, or to be contacted. Summarize '
            .'what they need in `summary`.';
    }

    /** @return array<string, mixed> */
    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => "The visitor's name."],
                'email' => ['type' => 'string', 'description' => 'Email address, if given.'],
                'phone' => ['type' => 'string', 'description' => 'Phone number, if given.'],
                'summary' => ['type' => 'string', 'description' => 'A short summary of what they need.'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $params, int $tenantId): string
    {
        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') {
            return 'I need your name to pass along — what should I put down?';
        }

        $lead = Lead::create([
            'name' => mb_substr($name, 0, 255),
            'email' => self::clean($params['email'] ?? null, 255),
            'phone' => self::clean($params['phone'] ?? null, 50),
            'message' => self::clean($params['summary'] ?? null, 2000),
            'source' => 'chatbot',
        ]);

        $admins = User::query()
            ->where('tenant_id', $tenantId)->where('role', 'tenant_admin')->where('is_active', true)
            ->get();
        Notification::send($admins, new LeadSubmitted($lead));

        return "Captured a lead for {$name}.";
    }

    private static function clean(mixed $v, int $max): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);

        return $v === '' ? null : mb_substr($v, 0, $max);
    }
}
