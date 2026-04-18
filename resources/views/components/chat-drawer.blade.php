<div x-data="{ open: false }" @open-chat.window="open = true">
    <!-- Overlay -->
    <div x-show="open" 
         x-transition.opacity 
         class="fixed inset-0 bg-gray-500 bg-opacity-75 z-40 transition-opacity" 
         @click="open = false" 
         style="display: none;">
    </div>

    <!-- Drawer -->
    <div class="fixed inset-y-0 right-0 z-50 flex max-w-full pl-10 sm:pl-16">
        <div x-show="open" 
             style="display: none;"
             x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400" 
             x-transition:enter-start="translate-x-full" 
             x-transition:enter-end="translate-x-0" 
             x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400" 
             x-transition:leave-start="translate-x-0" 
             x-transition:leave-end="translate-x-full" 
             class="w-screen max-w-md pointer-events-auto">
            
            <div class="flex h-full flex-col bg-white shadow-xl">
                <!-- Header -->
                <div class="px-4 py-6 bg-indigo-600 sm:px-6 flex items-center justify-between">
                    <h2 class="text-lg font-medium text-white" id="slide-over-title">Messages</h2>
                    <div class="ml-3 flex h-7 items-center">
                        <button type="button" class="rounded-md bg-indigo-600 text-indigo-200 hover:text-white focus:outline-none focus:ring-2 focus:ring-white" @click="open = false">
                            <span class="sr-only">Close panel</span>
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                
                <!-- Chat Content -->
                <div class="relative flex-1 p-0 overflow-hidden">
                    <iframe 
                        :src="open ? '{{ auth()->user()->isVerifiedSeller() ? route('seller.messages.index') : route('messages.index') }}?layout=minimal' : ''" 
                        class="w-full h-full border-0">
                    </iframe>
                </div>
            </div>
        </div>
    </div>
</div>