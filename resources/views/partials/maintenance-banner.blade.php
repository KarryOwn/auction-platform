@if(isset($maintenance_window) && $maintenance_window->isUpcomingWithinHours(2))
    <div class="border-b border-amber-300 bg-amber-100 text-amber-950">
        <div class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-3 text-sm sm:px-6 lg:px-8 md:flex-row md:items-center md:justify-between"
             x-data="{
                 target: new Date(@js($maintenance_window->scheduled_start->toIso8601String())).getTime(),
                 countdown: '',
                 init() {
                     const tick = () => {
                         const diff = this.target - Date.now();
                         if (diff <= 0) {
                             this.countdown = 'Starting now';
                             return;
                         }
                         const minutes = Math.floor(diff / 60000);
                         const hours = Math.floor(minutes / 60);
                         const remainingMinutes = minutes % 60;
                         this.countdown = hours > 0 ? `${hours}h ${remainingMinutes}m` : `${remainingMinutes}m`;
                     };
                     tick();
                     setInterval(tick, 60000);
                 }
             }">
            <p class="font-medium">
                <span class="mr-1">⚠</span>
                Scheduled maintenance on {{ $maintenance_window->scheduled_start->format('M d, Y') }}
                from {{ $maintenance_window->scheduled_start->format('H:i') }}
                to {{ $maintenance_window->scheduled_end->format('H:i') }}.
                Please save your work.
            </p>
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 md:text-sm">
                Starts in <span x-text="countdown"></span>
            </p>
        </div>
    </div>
@endif
