<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AiQuota;
use App\Services\Chat\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI assistant — one chat surface for every role; AssistantService picks the
 * context and tools from the user's role. The send endpoint is a JSON API
 * (the Vue page posts to it) and is rate-limited to 30 messages/hour/user.
 */
class ChatController extends Controller
{
    public function __construct(private AssistantService $assistant) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $session = ChatSession::query()->where('user_id', $user->id)->latest()->first();
        $messages = $session !== null
            ? $session->messages()->orderBy('id')->get()->map(fn (ChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])->all()
            : [];

        return Inertia::render('assistant/Index', [
            'messages' => $messages,
            'sessionId' => $session?->id,
            'suggestions' => $this->suggestions($user->role),
            'intro' => $this->intro($user->role),
        ]);
    }

    public function send(SendMessageRequest $request, AiQuota $quota): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $rateKey = "assistant:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return response()->json(['error' => 'Rate limit reached (30 messages/hour). Please wait a few minutes.'], 429);
        }

        $tenantId = $user->tenant_id;
        if ($tenantId !== null && ! $quota->enabled((int) $tenantId)) {
            return response()->json(['error' => 'The AI assistant is disabled for this company. Contact support to enable it.'], 403);
        }
        if ($tenantId !== null && $quota->remaining((int) $tenantId) <= 0) {
            return response()->json(['error' => 'Monthly AI allowance reached. Add a top-up or upgrade to continue.'], 429);
        }

        RateLimiter::hit($rateKey, 3600);

        $sessionId = $request->integer('session_id');
        $session = $sessionId > 0
            ? ChatSession::query()->where('id', $sessionId)->where('user_id', $user->id)->first()
            : null;
        $session ??= ChatSession::create(['user_id' => $user->id, 'context' => $user->role]);

        $reply = $this->assistant->reply($user, $session, (string) $request->string('message'));

        if ($tenantId !== null) {
            $quota->record((int) $tenantId);
        }

        return response()->json([
            'reply' => $reply,
            'session_id' => $session->id,
            'remaining' => max(0, 30 - RateLimiter::attempts($rateKey)),
            'ai_remaining' => $tenantId !== null ? $quota->remaining((int) $tenantId) : null,
        ]);
    }

    /**
     * Role-specific starter prompts.
     *
     * @return list<string>
     */
    /**
     * Example questions on an empty chat, for this kind of account. Questions only:
     * a tap sends one straight away, so none of them changes anything.
     *
     * @return list<string>
     */
    private function suggestions(string $role): array
    {
        return match ($role) {
            'tenant_admin' => [
                'How are my routes looking today?',
                'Who owes us money right now?',
                'Which chemicals are running low?',
                "Which pools haven't been tested recently?",
                'pH is 8.0 and alkalinity 140 in a 20,000-gallon pool. What should we add?',
            ],
            'agent' => [
                "What's on my route today?",
                'My pH reading is 8.2. What should I add?',
                'The water is cloudy after shocking. What’s going on?',
                'How do I troubleshoot a salt cell that isn’t producing?',
                'How should I clean a cartridge filter?',
            ],
            'customer' => [
                'When is my next service?',
                'What did my last water test show?',
                'How do I keep my pool clean between visits?',
                'Is it safe to swim right after a shock treatment?',
                'Why is my spa water foamy?',
            ],
            'super_admin' => [
                'What’s the ideal free chlorine for a salt pool?',
                'Explain LSI in plain terms.',
                'How do you close a pool for the winter?',
                'What causes black algae, and how do you get rid of it?',
            ],
            default => [],
        };
    }

    /** The line above those examples. */
    private function intro(string $role): string
    {
        return match ($role) {
            'tenant_admin' => 'Ask about your routes, customers, balances and stock, or anything about pool care.',
            'agent' => 'Ask about your route, or anything about water chemistry and pool equipment.',
            'customer' => 'Ask about your service and your pool, or how to look after it between visits.',
            default => 'Ask anything about pool and spa care.',
        };
    }
}
