{{-- Notification Bell Component --}}
@php
    $bellUser = auth()->user();
    $initialNotifications = $bellUser
        ? $bellUser->unreadNotifications->take(20)->map(fn ($n) => [
            'id' => $n->id,
            'data' => $n->data,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at->toISOString(),
        ])->values()->all()
        : [];
    $initialUnreadCount = $bellUser ? $bellUser->unreadNotifications()->count() : 0;
@endphp

<div x-data="{
        open: false,
        toast: null,
        notifications: {{ Js::from($initialNotifications) }},
        unreadCount: {{ $initialUnreadCount }},
        markingRead: false,

        init() {
            // Check if we recently marked all as read — if so, override the server count
            const markedAt = localStorage.getItem('notifications_marked_read_at');
            if (markedAt) {
                const elapsed = Date.now() - parseInt(markedAt, 10);
                // If marked within last 10 seconds, trust the client state
                if (elapsed < 10000) {
                    this.unreadCount = 0;
                    this.notifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
                } else {
                    localStorage.removeItem('notifications_marked_read_at');
                }
            }

            if (window.Echo && window.userId) {
                window.Echo.private('App.Models.User.' + window.userId)
                    .notification((notification) => {
                        this.notifications.unshift({
                            id: notification.id || Date.now().toString(),
                            data: notification,
                            read_at: null,
                            created_at: new Date().toISOString(),
                        });
                        this.unreadCount++;
                        // Clear the marked-read cache since we have new notifications
                        localStorage.removeItem('notifications_marked_read_at');

                        // Laravel broadcasts set 'type' to the class FQCN
                        // (e.g. 'App\\Notifications\\OutbidNotification'),
                        // so we derive the notification kind from the data itself.
                        const isOutbid = notification.outbid_amount !== undefined
                            && !notification.is_watcher;

                        this.toast = {
                            title: isOutbid
                                ? 'You\'ve been outbid!'
                                : (notification.title || notification.auction_title || 'New notification'),
                            body: notification.message || '',
                            auctionId: notification.auction_id || null,
                            conversationId: notification.conversation_id || null,
                        };

                        if (isOutbid) {
                            window.dispatchEvent(new CustomEvent('outbid-notification', {
                                detail: {
                                    auctionId: notification.auction_id,
                                    newAmount: notification.outbid_amount,
                                    type: 'outbid',
                                }
                            }));
                        }
                    });
            }
        },

        toggle() {
            this.open = !this.open;
            if (this.open && this.unreadCount > 0) {
                this.markAllRead();
            }
        },

        markAllRead() {
            this.notifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
            this.unreadCount = 0;
            this.markingRead = true;

            // Save timestamp so next page load knows we just marked all read
            localStorage.setItem('notifications_marked_read_at', Date.now().toString());

            // Use axios first for reliable feedback
            if (window.axios) {
                window.axios.post('/notifications/mark-all-read')
                    .then(() => { this.markingRead = false; })
                    .catch(e => {
                        console.error('Failed to mark notifications as read', e);
                        this.markingRead = false;
                    });
            }

            // Also register a beforeunload handler as safety net —
            // if user navigates away before axios completes, sendBeacon will fire
            const beaconHandler = () => {
                if (this.markingRead) {
                    const data = new FormData();
                    data.append('_token', document.querySelector('meta[name=\'csrf-token\']').content);
                    navigator.sendBeacon('/notifications/mark-all-read', data);
                }
                window.removeEventListener('beforeunload', beaconHandler);
            };
            window.addEventListener('beforeunload', beaconHandler);
        },

        handleClick(n) {
            if (n.data.conversation_id) {
                window.location.href = '/messages/' + n.data.conversation_id;
                return;
            }
            if (n.data.auction_id) {
                window.location.href = '/auctions/' + n.data.auction_id;
            }
        },

        timeAgo(dateStr) {
            const seconds = Math.floor((new Date() - new Date(dateStr)) / 1000);
            if (seconds < 60) return 'just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            return Math.floor(seconds / 86400) + 'd ago';
        }
    }" class="relative">
    <button @click="toggle()" class="relative p-1 text-gray-400 hover:text-gray-600 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        {{-- Unread badge --}}
        <span x-show="unreadCount > 0"
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full"
              x-transition></span>
    </button>

    {{-- Toast popup (auto-dismiss) --}}
    <div x-show="toast" x-transition.opacity.duration.300ms
         class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-xl border border-gray-200 z-[60] p-4"
         x-init="$watch('toast', val => { if (val) setTimeout(() => toast = null, 5000) })">
        <template x-if="toast">
            <div>
                <div class="flex items-start gap-2">
                    <span class="flex-shrink-0 mt-0.5 text-red-500">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92z" clip-rule="evenodd"/></svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900" x-text="toast.title"></p>
                        <p class="text-xs text-gray-500 mt-0.5" x-text="toast.body"></p>
                    </div>
                    <button @click="toast = null" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <a x-show="!toast.conversationId && toast.auctionId" :href="'/auctions/' + toast.auctionId"
                   class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">
                    View Auction →
                </a>
                <a x-show="toast.conversationId" :href="'/messages/' + toast.conversationId"
                   class="mt-2 inline-block text-xs font-medium text-indigo-600 hover:underline">
                    View Message →
                </a>
            </div>
        </template>
    </div>

    {{-- Dropdown --}}
    <div x-show="open"
         @click.outside="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50 overflow-hidden">
        <div class="p-3 border-b border-gray-100 flex items-center justify-between">
            <span class="font-semibold text-gray-900 text-sm">Notifications</span>
            <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs text-indigo-600 hover:underline">Mark all read</button>
        </div>

        <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <template x-if="notifications.length === 0">
                <div class="p-6 text-center text-gray-400 text-sm">No notifications</div>
            </template>
            <template x-for="n in notifications" :key="n.id">
                <div :class="n.read_at ? 'bg-white' : 'bg-blue-50'" class="p-3 hover:bg-gray-50 transition cursor-pointer" @click="handleClick(n)">
                    <div class="text-sm text-gray-900" x-text="n.data.message || n.data.preview || n.data.auction_title || 'Notification'"></div>
                    <div class="text-xs text-gray-400 mt-1" x-text="timeAgo(n.created_at)"></div>
                </div>
            </template>
        </div>
    </div>
</div>

