<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\AiQuota;
use App\Services\Chat\AssistantService;
use App\Support\SensitiveText;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AI assistant: one chat page for every role; AssistantService builds the
 * prompt and look-ups from the person's role. The send endpoint is a JSON API
 * (the Vue page posts to it), limited to 30 messages an hour per person, a
 * daily cap for customers and techs, and one message at a time per chat.
 *
 * A chat idle for 12 hours starts fresh when the page opens (the old one can
 * be continued), New conversation starts one on demand, and the page loads
 * the last 50 messages ("all" loads the rest). Chats a super admin has
 * while signed in as someone are kept apart ("support") and never shown to
 * that person.
 */
class ChatController extends Controller
{
    private const IDLE_HOURS = 12;

    private const PAGE = 50;

    public function __construct(private AssistantService $assistant) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        // "Continue" reopens the last chat with messages; otherwise the latest
        // chat, unless it has gone quiet, when the next message starts afresh.
        $continue = $request->boolean('continue');
        $session = $this->sessions($request)->when($continue, fn ($q) => $q->whereHas('messages'))->latest('id')->first();
        $last = $session?->messages()->max('created_at');
        $quiet = ! $continue && $last !== null && now()->diffInHours($last, true) >= self::IDLE_HOURS;
        if ($quiet) {
            $session = null;
        }

        $all = $request->boolean('all');
        $rows = $session?->messages()->whereIn('role', ['user', 'assistant'])->orderByDesc('id')
            ->when(! $all, fn ($q) => $q->limit(self::PAGE + 1))->get();

        return Inertia::render('assistant/Index', [
            'messages' => $rows?->take($all ? $rows->count() : self::PAGE)->reverse()->values()
                ->map(fn (ChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])->all() ?? [],
            'sessionId' => $session?->id,
            'hasEarlier' => ! $all && $rows !== null && $rows->count() > self::PAGE,
            'hasPrevious' => $quiet || ($session !== null && $last === null
                && $this->sessions($request)->whereKeyNot($session->id)->whereHas('messages')->exists()),
            'suggestions' => $this->suggestions($user->role),
            'intro' => $this->intro($user->role),
        ]);
    }

    /** New conversation: an empty chat becomes the latest (reusing one that's already empty). */
    public function fresh(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $latest = $this->sessions($request)->latest('id')->first();
        if ($latest === null || $latest->messages()->exists()) {
            ChatSession::create(['user_id' => $user->id, 'context' => $this->impersonating($request) ? 'support' : $user->role]);
        }

        return redirect()->route('assistant.index');
    }

    public function send(SendMessageRequest $request, AiQuota $quota): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $rateKey = "assistant:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return response()->json(['error' => 'Rate limit reached (30 messages an hour). Please wait a few minutes.'], 429);
        }
        $daily = config("ai.daily_limits.{$user->role}");
        $dayKey = "assistant-day:{$user->id}";
        if (is_numeric($daily) && RateLimiter::tooManyAttempts($dayKey, (int) $daily)) {
            return response()->json(['error' => "That's today's limit of {$daily} questions. The assistant will be back tomorrow."], 429);
        }

        $tenantId = $user->tenant_id;
        if ($tenantId !== null && ! $quota->enabled((int) $tenantId)) {
            return response()->json(['error' => 'The AI assistant is disabled for this company. Contact support to enable it.'], 403);
        }
        if ($tenantId !== null && $quota->remainingFor((int) $tenantId, $user->role) <= 0) {
            return response()->json(['error' => $user->role === 'customer'
                ? 'The assistant is resting until next month. For anything urgent, contact your pool company.'
                : 'Monthly AI allowance reached. Add a top-up or upgrade to continue.'], 429);
        }

        $sessionId = $request->integer('session_id');
        $session = $sessionId > 0 ? $this->sessions($request)->whereKey($sessionId)->first() : null;
        $session ??= ChatSession::create(['user_id' => $user->id, 'context' => $this->impersonating($request) ? 'support' : $user->role]);

        // One message at a time per chat (two tabs would interleave the history).
        try {
            $reply = Cache::lock("assistant:chat:{$session->id}", 120)->block(15, fn (): string => $this->assistant->reply($user, $session, (string) $request->string('message')));
        } catch (LockTimeoutException) {
            return response()->json(['error' => 'Still answering your last message. Try again in a moment.'], 429);
        }

        RateLimiter::hit($rateKey, 3600);
        if (is_numeric($daily)) {
            RateLimiter::hit($dayKey, 86400);
        }
        if ($tenantId !== null) {
            $quota->record((int) $tenantId);
        }

        return response()->json([
            'reply' => $reply,
            'message' => SensitiveText::redact((string) $request->string('message')), // as saved: card numbers blanked
            'session_id' => $session->id,
            'remaining' => max(0, 30 - RateLimiter::attempts($rateKey)),
            'ai_remaining' => $tenantId !== null ? $quota->remaining((int) $tenantId) : null,
        ]);
    }

    /**
     * This person's chats: their own, or, while a super admin is signed in as
     * them, only the separate support ones.
     *
     * @return Builder<ChatSession>
     */
    private function sessions(Request $request): Builder
    {
        return ChatSession::query()
            ->where('user_id', $request->user()?->id)
            ->where('context', $this->impersonating($request) ? '=' : '!=', 'support');
    }

    private function impersonating(Request $request): bool
    {
        return $request->session()->has('impersonator_id');
    }

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
