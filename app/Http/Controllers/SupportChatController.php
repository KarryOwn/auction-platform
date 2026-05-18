<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use App\Notifications\SupportEscalationNotification;
use App\Services\SupportChatContextBuilder;
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

    public function __construct(private readonly SupportChatContextBuilder $contextBuilder)
    {
    }

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

        $platformContext = $this->contextBuilder->build($user);
        $aiResponse = $this->askOpenRouter($history, $user, $platformContext);

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

    private function askOpenRouter(array $history, ?User $user, string $platformContext): string
    {
        $apiKey = config('services.openrouter.api_key');

        if (blank($apiKey)) {
            Log::warning('SupportChatController: OpenRouter API key is not configured.');

            return $this->fallbackSupportReply($history, $user);
        }

        $messages = [[
            'role' => 'system',
            'content' => $this->buildSystemPrompt($user, $platformContext),
        ]];

        foreach ($history as $message) {
            $messages[] = [
                'role' => $message['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $message['content'],
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withToken($apiKey)
                ->withHeaders(array_filter([
                    'HTTP-Referer' => config('services.openrouter.referer'),
                    'X-OpenRouter-Title' => config('services.openrouter.title'),
                ]))
                ->post('https://openrouter.ai/api/v1/chat/completions', [
                    'model' => config('services.openrouter.model', 'tencent/hy3-preview:free'),
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

            if ($response->failed()) {
                Log::warning('SupportChatController: OpenRouter API request failed', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 1000),
                ]);

                return $this->fallbackSupportReply($history, $user);
            }

            $data = $response->json();
            $text = data_get($data, 'choices.0.message.content');

            if (is_string($text) && trim($text) !== '') {
                return trim($text);
            }

            Log::warning('SupportChatController: OpenRouter API returned no answer text', [
                'finish_reason' => data_get($data, 'choices.0.finish_reason'),
                'error' => data_get($data, 'error.message'),
            ]);

            return $this->fallbackSupportReply($history, $user);
        } catch (\Throwable $e) {
            Log::error('SupportChatController: OpenRouter API error', [
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackSupportReply($history, $user);
        }
    }

    private function fallbackSupportReply(array $history, ?User $user): string
    {
        $latest = $history === []
            ? ''
            : (string) ($history[array_key_last($history)]['content'] ?? '');
        $latestMessage = Str::lower($latest);

        if (Str::contains($latestMessage, ['account', 'profile', 'login', 'password', 'email'])) {
            if ($user) {
                $sellerStatus = $user->seller_application_status
                    ? " Your seller application status is {$user->seller_application_status}."
                    : '';

                return "Your account is active as a {$user->role}. Wallet, bids, invoices, and profile settings are available from your dashboard.{$sellerStatus} Click \"Talk to a human\" if you need staff to review a private account record.";
            }

            return 'For account help, sign in to view your dashboard, wallet, bids, invoices, and profile settings. If you cannot access the account, click "Talk to a human" and include the email or username you need help with.';
        }

        if (Str::contains($latestMessage, ['wallet', 'fund', 'balance', 'deposit', 'money'])) {
            if ($user) {
                return 'Your wallet page shows available balance, held bid funds, pending payouts, deposits, withdrawals, payments, and refunds. If a balance or transaction looks wrong, click "Talk to a human" so support can review the specific record.';
            }

            return 'Wallet actions are available after signing in from your account wallet page. If a payment or balance looks wrong, click "Talk to a human" so support can review the transaction.';
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

    private function buildSystemPrompt(?User $user, string $platformContext): string
    {
        $context = $user
            ? "The user is logged in as {$user->name} (role: {$user->role}). "
            : 'The user is not logged in. ';

        return <<<PROMPT
You are a helpful customer support assistant for an online auction platform.
{$context}
Answer questions about: bidding, auction rules, payment, wallets, seller applications, disputes, and account issues.
Use the platform context below as the source of truth. Guests can only receive public platform information. Logged-in users can receive answers about their own records only.
Keep responses concise (2-3 sentences max). If the answer requires staff action, sensitive investigation, or records not present in the context, suggest they click "Talk to a human" to escalate.
Do not make up platform-specific URLs, prices, policies, user records, or staff actions you are not certain about.

Platform context:
{$platformContext}
PROMPT;
    }
}
