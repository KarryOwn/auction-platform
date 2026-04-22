<div
    x-data="supportWidget()"
    x-init="init()"
    class="fixed bottom-4 right-4 z-[110] flex flex-col items-end gap-3"
>
    <div
        x-show="open"
        x-transition
        class="w-[min(92vw,24rem)] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl"
        style="display: none;"
    >
        <div class="flex items-center justify-between bg-slate-900 px-4 py-3 text-white">
            <div>
                <h3 class="text-sm font-semibold">Support</h3>
                <p class="text-xs text-slate-300">Ask a question or talk to our team.</p>
            </div>
            <button type="button" class="rounded-md p-1 text-slate-300 hover:bg-slate-800 hover:text-white" @click="open = false">
                <span class="sr-only">Close support chat</span>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="h-80 overflow-y-auto bg-slate-50 px-4 py-4 space-y-3" x-ref="messages">
            <template x-if="messages.length === 0">
                <div class="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-6 text-center text-sm text-slate-500">
                    Ask about bidding, payments, disputes, wallets, seller applications, or account issues.
                </div>
            </template>

            <template x-for="message in messages" :key="message.id ?? `${message.role}-${message.created_at}`">
                <div :class="message.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                    <div
                        :class="message.role === 'user'
                            ? 'max-w-[85%] rounded-2xl rounded-br-md bg-indigo-600 px-4 py-3 text-sm text-white'
                            : 'max-w-[85%] rounded-2xl rounded-bl-md bg-white px-4 py-3 text-sm text-slate-800 shadow-sm border border-slate-200'"
                    >
                        <p class="whitespace-pre-line" x-text="message.body"></p>
                    </div>
                </div>
            </template>
        </div>

        <div class="border-t border-gray-200 bg-white px-4 py-3">
            <div x-show="canEscalate && !escalated" class="mb-3">
                <button
                    type="button"
                    class="w-full rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900 hover:bg-amber-100"
                    @click="escalate()"
                >
                    Talk to a human
                </button>
            </div>
            <form class="space-y-3" @submit.prevent="send()">
                <textarea
                    x-model="draft"
                    rows="3"
                    maxlength="2000"
                    class="w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="How can we help?"
                ></textarea>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs text-slate-400" x-text="statusText"></p>
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        :disabled="sending || !draft.trim()"
                    >
                        <span x-text="sending ? 'Sending...' : 'Send'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <button
        type="button"
        class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-indigo-600 text-white shadow-xl transition hover:bg-indigo-700"
        @click="toggle()"
    >
        <span class="sr-only">Open support chat</span>
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4v-4z"/>
        </svg>
    </button>
</div>

@once
    @push('scripts')
        <script>
            if (!window.supportWidget) {
                window.supportWidget = function () {
                    return {
                        open: false,
                        sending: false,
                        conversationId: null,
                        draft: '',
                        messages: [],
                        canEscalate: false,
                        escalated: false,
                        statusText: 'AI support replies instantly.',
                        init() {
                            const storedId = window.localStorage.getItem('support_conversation_id');
                            if (storedId) {
                                this.conversationId = storedId;
                            }
                        },
                        toggle() {
                            this.open = !this.open;
                            if (this.open && this.conversationId && this.messages.length === 0) {
                                this.loadConversation();
                            }
                        },
                        async loadConversation() {
                            try {
                                const response = await fetch(`/support/chat/${this.conversationId}`, {
                                    headers: { Accept: 'application/json' },
                                });
                                if (!response.ok) {
                                    return;
                                }
                                const data = await response.json();
                                this.messages = data.messages || [];
                                this.escalated = data.status === 'escalated' || data.status === 'closed';
                                this.canEscalate = (this.messages.filter((message) => message.is_ai).length >= 2) && !this.escalated;
                                this.$nextTick(() => this.scrollToBottom());
                            } catch (_) {
                                this.statusText = 'Unable to load previous support messages.';
                            }
                        },
                        async send() {
                            if (this.sending || !this.draft.trim()) {
                                return;
                            }

                            const body = this.draft.trim();
                            this.messages.push({
                                id: `local-${Date.now()}`,
                                role: 'user',
                                body,
                                is_ai: false,
                                created_at: new Date().toISOString(),
                            });
                            this.draft = '';
                            this.sending = true;
                            this.statusText = 'Waiting for AI support...';
                            this.$nextTick(() => this.scrollToBottom());

                            try {
                                const response = await fetch(`{{ route('support.chat.send') }}`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                    },
                                    body: JSON.stringify({
                                        message: body,
                                        conversation_id: this.conversationId,
                                    }),
                                });

                                const data = await response.json();
                                if (!response.ok) {
                                    throw new Error(data.message || 'Support request failed.');
                                }

                                this.conversationId = data.conversation_id;
                                window.localStorage.setItem('support_conversation_id', this.conversationId);
                                this.messages.push({
                                    id: `ai-${Date.now()}`,
                                    role: 'assistant',
                                    body: data.message,
                                    is_ai: true,
                                    created_at: new Date().toISOString(),
                                });
                                this.canEscalate = Boolean(data.can_escalate) && !this.escalated;
                                this.statusText = this.canEscalate
                                    ? 'Need more help? You can talk to a human.'
                                    : 'AI support replied.';
                            } catch (error) {
                                this.messages.push({
                                    id: `error-${Date.now()}`,
                                    role: 'assistant',
                                    body: error.message || 'Support is temporarily unavailable.',
                                    is_ai: true,
                                    created_at: new Date().toISOString(),
                                });
                                this.statusText = 'Support is temporarily unavailable.';
                            } finally {
                                this.sending = false;
                                this.$nextTick(() => this.scrollToBottom());
                            }
                        },
                        async escalate() {
                            if (!this.conversationId) {
                                return;
                            }

                            try {
                                const response = await fetch(`/support/chat/${this.conversationId}/escalate`, {
                                    method: 'POST',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                                    },
                                });
                                const data = await response.json();
                                if (!response.ok) {
                                    throw new Error(data.message || 'Unable to escalate support conversation.');
                                }
                                this.escalated = true;
                                this.canEscalate = false;
                                this.messages.push({
                                    id: `escalated-${Date.now()}`,
                                    role: 'assistant',
                                    body: data.message,
                                    is_ai: false,
                                    created_at: new Date().toISOString(),
                                });
                                this.statusText = 'Human support has been notified.';
                                this.$nextTick(() => this.scrollToBottom());
                            } catch (error) {
                                this.statusText = error.message || 'Unable to escalate support conversation.';
                            }
                        },
                        scrollToBottom() {
                            const node = this.$refs.messages;
                            if (node) {
                                node.scrollTop = node.scrollHeight;
                            }
                        },
                    };
                };
            }
        </script>
    @endpush
@endonce
