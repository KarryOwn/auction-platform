<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportEscalationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    private const SESSION_KEY = 'support_conversation_ids';

    public function show(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        return response()->json([
            'conversation_id' => $conversation->id,
            'status' => $conversation->status,
            'messages' => $conversation->messages()
                ->orderBy('created_at')
                ->get(['id', 'role', 'body', 'is_ai', 'created_at']),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer', 'exists:support_conversations,id'],
        ]);

        $this->ensureNotRateLimited($request);

        $user = $request->user();
        $isNewConversation = ! isset($validated['conversation_id']);

        $conversation = $isNewConversation
            ? SupportConversation::create([
                'user_id' => $user?->id,
                'status' => SupportConversation::STATUS_OPEN,
                'channel' => 'widget',
                'last_message_at' => now(),
            ])
            : SupportConversation::findOrFail($validated['conversation_id']);

        $this->rememberConversation($request, $conversation);
        $this->authorizeConversation($request, $conversation);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'body' => $validated['message'],
            'is_ai' => false,
        ]);

        $history = SupportMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get()
            ->map(fn (SupportMessage $message) => [
                'role' => $message->role === 'admin' ? 'assistant' : $message->role,
                'content' => $message->body,
            ])
            ->all();

        $aiResponse = $this->askGemini($history, $user);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'body' => $aiResponse,
            'is_ai' => true,
        ]);

        $aiCount = $conversation->messages()->where('is_ai', true)->count();

        $conversation->update([
            'last_message_at' => now(),
            'status' => $conversation->status === SupportConversation::STATUS_ESCALATED
                ? SupportConversation::STATUS_ESCALATED
                : SupportConversation::STATUS_AI_HANDLED,
        ]);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message' => $aiResponse,
            'is_ai' => true,
            'can_escalate' => $aiCount >= 2,
        ]);
    }

    public function escalate(Request $request, SupportConversation $conversation): JsonResponse
    {
        $this->authorizeConversation($request, $conversation);

        $conversation->update([
            'status' => SupportConversation::STATUS_ESCALATED,
            'last_message_at' => now(),
        ]);

        $admins = User::query()->where('role', User::ROLE_ADMIN)->get();
        Notification::send($admins, new SupportEscalationNotification($conversation));

        return response()->json([
            'message' => 'Connected to support team. A member will reply shortly.',
        ]);
    }

    private function authorizeConversation(Request $request, SupportConversation $conversation): void
    {
        $user = $request->user();

        if ($user && $conversation->user_id === $user->id) {
            return;
        }

        if ($conversation->user_id === null && in_array($conversation->id, $request->session()->get(self::SESSION_KEY, []), true)) {
            return;
        }

        abort(403);
    }

    private function rememberConversation(Request $request, SupportConversation $conversation): void
    {
        $ids = $request->session()->get(self::SESSION_KEY, []);

        if (! in_array($conversation->id, $ids, true)) {
            $ids[] = $conversation->id;
            $request->session()->put(self::SESSION_KEY, array_slice($ids, -10));
        }
    }

    private function ensureNotRateLimited(Request $request): void
    {
        $key = 'support-chat:' . ($request->user()?->id ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Support chat rate limit reached. Please try again later.');
        }

        RateLimiter::hit($key, 3600);
    }

    private function askGemini(array $history, ?User $user): string
    {
        $apiKey = config('services.gemini.api_key');

        if (blank($apiKey)) {
            Log::warning('SupportChatController: Gemini API key is not configured.');

            return $this->fallbackSupportReply($history);
        }

        $systemPrompt = $this->buildSystemPrompt($user);
        $contents = [[
            'role' => 'user',
            'parts' => [['text' => $systemPrompt]],
        ]];

        foreach ($history as $message) {
            $contents[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $message['content']]],
            ];
        }

        try {
            $response = Http::timeout(15)->post(
                'https://generativelanguage.googleapis.com/v1/models/' . config('services.gemini.model', 'gemini-2.0-flash') . ':generateContent?key=' . $apiKey,
                [
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 500,
                        'temperature' => 0.7,
                    ],
                ]
            );

            if ($response->failed()) {
                Log::warning('SupportChatController: Gemini API request failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1000),
                ]);

                return $this->fallbackSupportReply($history);
            }

            $data = $response->json();
            $text = data_get($data, 'candidates.0.content.parts.0.text');

            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }

            Log::warning('SupportChatController: Gemini API returned no answer text', [
                'finish_reason' => data_get($data, 'candidates.0.finishReason'),
                'prompt_feedback' => data_get($data, 'promptFeedback'),
                'error' => data_get($data, 'error.message'),
            ]);

            return $this->fallbackSupportReply($history);
        } catch (\Throwable $e) {
            Log::error('SupportChatController: Gemini API error', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackSupportReply($history);
        }
    }

    private function fallbackSupportReply(array $history): string
    {
        $latest = $history === []
            ? ''
            : (string) ($history[array_key_last($history)]['content'] ?? '');
        $latestMessage = Str::lower($latest);

        if (Str::contains($latestMessage, ['wallet', 'fund', 'balance', 'deposit', 'money'])) {
            return 'Wallet actions are available from your account wallet page. If a payment or balance looks wrong, click "Talk to a human" so support can review the transaction.';
        }

        if (Str::contains($latestMessage, ['bid', 'bidding', 'outbid', 'auction'])) {
            return 'For bidding issues, check that the auction is active and your bid meets the minimum increment. If the auction or bid state looks incorrect, click "Talk to a human" and include the auction name.';
        }

        if (Str::contains($latestMessage, ['seller', 'application', 'verify', 'verification'])) {
            return 'Seller applications are reviewed from the seller area after submission. If your status has not changed or you need verification help, click "Talk to a human" so staff can check it.';
        }

        if (Str::contains($latestMessage, ['dispute', 'refund', 'return', 'payment', 'invoice'])) {
            return 'For disputes, refunds, returns, or invoices, gather the auction details and payment reference. Click "Talk to a human" so support can review the account-specific records.';
        }

        return 'Support AI is temporarily unavailable, but I can still route your request. Please click "Talk to a human" and include the auction, payment, or account detail you need help with.';
    }

    private function buildSystemPrompt(?User $user): string
    {
        $context = $user
            ? "The user is logged in as {$user->name} (role: {$user->role}). "
            : 'The user is not logged in. ';

        return <<<PROMPT
You are a helpful customer support assistant for an online auction platform.
{$context}
Answer questions about: bidding, auction rules, payment, wallets, seller applications, disputes, and account issues.
Keep responses concise (2-3 sentences max). If you cannot confidently answer or if the user is frustrated, suggest they click "Talk to a human" to escalate.
Do not make up platform-specific URLs, prices, or policies you are not certain about.
PROMPT;
    }
}
