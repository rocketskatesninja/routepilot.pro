<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\AiQuota;
use App\Services\Chat\AiCredentials;
use App\Services\Chat\PublicAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public lead-capture chatbot endpoint (unauthenticated, per tenant). A visitor
 * token resumes the anonymous conversation; the tenant's AI allowance is metered
 * the same as the authed assistants. Rate-limited per IP at the route.
 */
class PublicChatController extends Controller
{
    public function send(Request $request, Tenant $tenant, AiQuota $quota, PublicAssistant $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'visitor_token' => ['nullable', 'string', 'size:36'],
        ]);

        app()->instance('tenant_id', $tenant->id);

        // Gate: AI must be configured for this tenant and within its monthly allowance.
        [, $key] = app(AiCredentials::class)->for($tenant->id);
        if ($key === '' || ! $quota->enabled($tenant->id) || $quota->remaining($tenant->id) <= 0) {
            return response()->json([
                'reply' => "Thanks for reaching out! Our chat assistant isn't available right now — please use the contact form and we'll get back to you shortly.",
                'unavailable' => true,
            ]);
        }

        $session = $this->session($tenant, $data['visitor_token'] ?? null);
        $reply = $assistant->reply($tenant, $session, $data['message']);
        $quota->record($tenant->id);

        return response()->json(['reply' => $reply, 'visitor_token' => $session->visitor_token]);
    }

    /** Resume the visitor's session by token (scoped to this tenant) or start a new one. */
    private function session(Tenant $tenant, ?string $token): ChatSession
    {
        if ($token !== null) {
            $existing = ChatSession::query()
                ->where('tenant_id', $tenant->id)
                ->where('visitor_token', $token)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return ChatSession::create([
            'user_id' => null,
            'tenant_id' => $tenant->id,
            'visitor_token' => (string) Str::uuid(),
            'context' => 'public_lead',
        ]);
    }
}
