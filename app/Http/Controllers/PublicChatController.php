<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\AiQuota;
use App\Services\Chat\AiCredentials;
use App\Services\Chat\PublicAssistant;
use App\Support\SensitiveText;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Public lead-capture chatbot endpoint (unauthenticated, per tenant). A visitor
 * token resumes the anonymous conversation. Rate-limited per IP at the route;
 * website visitors can't use the staff's share of the company's monthly AI
 * allowance (like customers); one message at a time per conversation.
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

        // Gate: AI must be configured for this tenant, and the visitors' share of its allowance not used up.
        [, $key] = app(AiCredentials::class)->for($tenant->id);
        if ($key === '' || ! $quota->enabled($tenant->id) || $quota->remainingFor($tenant->id, 'public') <= 0) {
            return response()->json(['reply' => PublicAssistant::UNAVAILABLE, 'unavailable' => true]);
        }

        $session = $this->session($tenant, $data['visitor_token'] ?? null);
        try {
            $reply = Cache::lock("public-chat:{$session->id}", 60)->block(10, fn (): string => $assistant->reply($tenant, $session, $data['message']));
        } catch (LockTimeoutException) {
            return response()->json(['reply' => 'One moment, I’m still answering your last message.', 'visitor_token' => $session->visitor_token]);
        }

        return response()->json([
            'reply' => $reply,
            'message' => SensitiveText::redact($data['message']), // as saved: card numbers blanked
            'visitor_token' => $session->visitor_token,
        ]);
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
