<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportInboxController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status');

        $conversations = SupportConversation::query()
            ->with(['user:id,name,email', 'assignee:id,name'])
            ->withCount('messages')
            ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->whereIn('status', [
                SupportConversation::STATUS_OPEN,
                SupportConversation::STATUS_AI_HANDLED,
                SupportConversation::STATUS_ESCALATED,
            ]))
            ->orderByDesc('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.support.index', compact('conversations', 'status'));
    }

    public function show(SupportConversation $conversation): View
    {
        $conversation->load([
            'user:id,name,email',
            'assignee:id,name',
            'messages' => fn ($query) => $query->orderBy('created_at'),
        ]);

        return view('admin.support.show', compact('conversation'));
    }

    public function reply(Request $request, SupportConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        SupportMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'admin',
            'body' => $validated['body'],
            'is_ai' => false,
        ]);

        $conversation->update([
            'assigned_to' => $request->user()->id,
            'last_message_at' => now(),
            'status' => SupportConversation::STATUS_ESCALATED,
        ]);

        return redirect()->route('admin.support.show', $conversation)
            ->with('success', 'Reply sent.');
    }

    public function close(SupportConversation $conversation): RedirectResponse
    {
        $conversation->update([
            'status' => SupportConversation::STATUS_CLOSED,
            'last_message_at' => now(),
        ]);

        return redirect()->route('admin.support.index')
            ->with('success', 'Support conversation closed.');
    }
}
