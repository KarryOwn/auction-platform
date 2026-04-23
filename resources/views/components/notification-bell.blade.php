{{-- Notification Bell Component --}}
<style>[x-cloak] { display: none !important; }</style>
@php
    $bellUser = auth()->user();
    $initialNotifications = $bellUser
        ? $bellUser->notifications()->latest()->limit(20)->get()->map(fn ($n) => [
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
                            applicationId: notification.application_id || null,
                            url: notification.url || null,
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
            if (n.data.url) {
                window.location.href = n.data.url;
                return;
            }
            if (n.data.support_conversation_id) {
                window.location.href = '/admin/support/' + n.data.support_conversation_id;
                return;
            }
            if (n.data.application_id) {
                window.location.href = '/admin/seller-applications/' + n.data.application_id;
                return;
            }
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
    <button @click="toggle()" 
            class="relative p-2 text-gray-500 hover:text-indigo-600 transition-colors duration-300 rounded-full hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
            :class="{ 'animate-pulse text-indigo-600': unreadCount > 0 }">
        <svg class="w-6 h-6 transform transition-transform duration-300 hover:rotate-12 hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        {{-- Unread badge --}}
        <span x-cloak x-show="unreadCount > 0"
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute top-0 right-0 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-bold text-white bg-gradient-to-r from-red-500 to-rose-600 rounded-full ring-2 ring-white shadow-sm transform scale-100 transition-all shadow-red-500/40"
              x-transition:enter="transition ease-out duration-300"
              x-transition:enter-start="scale-0 opacity-0"
              x-transition:enter-end="scale-100 opacity-100"
              x-transition:leave="transition ease-in duration-200"
              x-transition:leave-start="scale-100 opacity-100"
              x-transition:leave-end="scale-0 opacity-0"></span>
        <span x-cloak x-show="unreadCount > 0" class="absolute top-0 right-0 w-full h-full rounded-full animate-ping bg-red-400 opacity-20"></span>
    </button>

    {{-- Toast popup (auto-dismiss) --}}
    <div x-cloak x-show="toast" 
         x-transition:enter="transition-all ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition-all ease-in duration-200"
         x-transition:leave-start="opacity-100 sm:scale-100"
         x-transition:leave-end="opacity-0 sm:scale-95"
         class="absolute right-0 top-12 mt-2 w-80 bg-white/90 backdrop-blur-md rounded-2xl shadow-2xl border border-white/50 z-[60] p-4 overflow-hidden"
         x-init="$watch('toast', val => { if (val) setTimeout(() => toast = null, 5000) })">
         
         <div class="absolute inset-0 bg-gradient-to-br from-brand-soft/70 to-orange-50/60 pointer-events-none"></div>
         <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-brand to-brand-accent pointer-events-none rounded-l-2xl"></div>
         
        <template x-if="toast">
            <div class="relative">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-brand-soft flex items-center justify-center shadow-inner mt-0.5">
                        <svg class="w-4 h-4 text-brand animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 leading-tight tracking-tight" x-text="toast.title"></p>
                        <p class="text-xs text-gray-600 mt-1 line-clamp-2 leading-relaxed" x-text="toast.body"></p>
                    </div>
                    <button @click="toast = null" class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-700 bg-gray-50 hover:bg-gray-100 rounded-full transition-colors focus:outline-none">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                    </button>
                </div>
                <div class="mt-3 ml-11 flex items-center gap-3">
                    <a x-show="!toast.conversationId && toast.auctionId" :href="'/auctions/' + toast.auctionId"
                       class="inline-flex items-center text-xs font-semibold text-brand hover:text-brand-hover transition-colors group">
                        View Auction 
                        <svg class="w-3 h-3 ml-1 transform transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    <a x-show="toast.url" :href="toast.url"
                       class="inline-flex items-center text-xs font-semibold text-brand hover:text-brand-hover transition-colors group">
                        Open
                        <svg class="w-3 h-3 ml-1 transform transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    <a x-show="!toast.url && toast.conversationId" :href="'/messages/' + toast.conversationId"
                       class="inline-flex items-center text-xs font-semibold text-brand hover:text-brand-hover transition-colors group">
                        View Message
                        <svg class="w-3 h-3 ml-1 transform transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                    <a x-show="!toast.url && toast.applicationId" :href="'/admin/seller-applications/' + toast.applicationId"
                       class="inline-flex items-center text-xs font-semibold text-brand hover:text-brand-hover transition-colors group">
                        Review Application
                        <svg class="w-3 h-3 ml-1 transform transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </a>
                </div>
            </div>
        </template>
    </div>

    {{-- Dropdown --}}
    <div x-cloak x-show="open"
         @click.outside="open = false"
         x-transition:enter="transition-all ease-out duration-300 origin-top-right"
         x-transition:enter-start="opacity-0 scale-90 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition-all ease-in duration-200 origin-top-right"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-90 translate-y-4"
         class="absolute right-0 mt-3 w-80 sm:w-96 bg-white/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-gray-100 z-50 overflow-hidden ring-1 ring-black ring-opacity-5">
         
         <div class="absolute inset-0 bg-gradient-to-br from-white/60 to-gray-50/60 pointer-events-none"></div>

        <div class="relative p-4 border-b border-gray-100/80 flex items-center justify-between bg-white/40 backdrop-blur-sm">
            <h3 class="font-bold text-gray-900 text-sm flex items-center gap-2">
                Notifications
                <span x-show="unreadCount > 0" class="bg-brand-soft text-brand text-[10px] px-2 py-0.5 rounded-full font-bold" x-text="unreadCount + ' New'"></span>
            </h3>
            <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs font-semibold text-brand hover:text-brand-hover transition-colors flex items-center gap-1 group focus:outline-none">
                <svg class="w-3 h-3 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Mark all read
            </button>
        </div>

        <div class="relative max-h-[22rem] overflow-y-auto overscroll-contain">
            <template x-if="notifications.length === 0">
                <div class="p-8 text-center flex flex-col items-center justify-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-900">All caught up!</p>
                    <p class="text-xs text-gray-500 mt-1">No new notifications for now.</p>
                </div>
            </template>
            <div class="divide-y divide-gray-100/50">
                <template x-for="n in notifications" :key="n.id">
                    <div :class="n.read_at ? 'bg-transparent hover:bg-gray-50/80' : 'bg-indigo-50/40 hover:bg-indigo-50/70'" 
                         class="p-4 transition-all duration-200 cursor-pointer group relative overflow-hidden" 
                         @click="handleClick(n)">
                        
                        <!-- Unread Indicator Line -->
                        <div x-show="!n.read_at" class="absolute left-0 top-0 bottom-0 w-1 bg-indigo-500"></div>

                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 mt-0.5">
                                <div :class="n.read_at ? 'bg-gray-100 text-gray-400' : 'bg-indigo-100 text-indigo-500 group-hover:scale-110 group-hover:bg-indigo-500 group-hover:text-white'" class="w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 shadow-sm">
                                    <svg x-show="n.data.conversation_id" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
                                    <svg x-show="n.data.outbid_amount !== undefined" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg>
                                    <svg x-show="!n.data.conversation_id && n.data.outbid_amount === undefined" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p :class="n.read_at ? 'text-gray-600 font-normal' : 'text-gray-900 font-bold'" 
                                   class="text-sm leading-snug line-clamp-2" 
                                   x-text="n.data.message || n.data.preview || n.data.auction_title || 'Notification'"></p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="text-[11px] text-gray-400 font-medium" x-text="timeAgo(n.created_at)"></span>
                                    <span x-show="!n.read_at" class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-pulse"></span>
                                </div>
                            </div>
                            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg class="w-4 h-4 text-gray-400 group-hover:text-indigo-500 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
