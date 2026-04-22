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

        $admins = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_MODERATOR])->get();
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
                'https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=' . config('services.gemini.api_key'),
                [
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => 500,
                        'temperature' => 0.7,
                    ],
                ]
            );

            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text']
                ?? "I'm sorry, I couldn't process your request. Please try again.";
        } catch (\Throwable $e) {
            Log::error('SupportChatController: Gemini API error', [
                'error' => $e->getMessage(),
            ]);

            return 'I\'m temporarily unavailable. Please click "Talk to a human" to connect with our support team.';
        }
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
