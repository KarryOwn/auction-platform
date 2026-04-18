@props([
    'endsAt',
    'snipeThreshold' => 30,
    'size' => 'md',
    'showLabel' => true,
])

@php
    $sizeClasses = match ($size) {
        'sm' => 'text-sm font-mono',
        'lg' => 'text-3xl font-mono font-bold',
        default => 'text-lg font-mono font-semibold',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col']) }}
    x-data="{
        endTime: new Date('{{ $endsAt }}').getTime(),
        snipeThreshold: {{ $snipeThreshold }},
        days: 0,
        hours: '00',
        minutes: '00',
        seconds: '00',
        isEnded: false,
        isSnipeWarning: false,
        timer: null,
        init() {
            window.updateAuctionEndTime = (newIsoString) => {
                this.endTime = new Date(newIsoString).getTime();
                this.calculate();
                if (this.isEnded) {
                    this.isEnded = false;
                    this.start();
                }
            };
            this.start();
        },
        start() {
            this.calculate();
            if (!this.isEnded) {
                this.timer = setInterval(() => {
                    this.calculate();
                }, 1000);
            }
        },
        calculate() {
            let now = Date.now();
            let diff = this.endTime - now;
            
            if (diff <= 0) {
                this.isEnded = true;
                this.isSnipeWarning = false;
                this.days = 0;
                this.hours = '00';
                this.minutes = '00';
                this.seconds = '00';
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
                return;
            }
            
            this.isEnded = false;
            this.isSnipeWarning = (diff / 1000) <= this.snipeThreshold;
            
            this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
            this.hours = String(Math.floor((diff / (1000 * 60 * 60)) % 24)).padStart(2, '0');
            this.minutes = String(Math.floor((diff / 1000 / 60) % 60)).padStart(2, '0');
            this.seconds = String(Math.floor((diff / 1000) % 60)).padStart(2, '0');
        },
        destroy() {
            if (this.timer) {
                clearInterval(this.timer);
            }
        }
    }">

    @if ($showLabel)
        <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">Time remaining</span>
    @endif

    <div class="{{ $sizeClasses }} transition-colors duration-300"
         :class="{
            'text-gray-400': isEnded,
            'text-orange-600 animate-pulse': isSnipeWarning && !isEnded,
            'text-gray-800 dark:text-gray-100': !isEnded && !isSnipeWarning
         }">
        
        <template x-if="isEnded">
            <span>Ended</span>
        </template>

        <template x-if="!isEnded">
            <span class="inline-flex gap-1.5">
                <template x-if="days > 0">
                    <span x-text="days + 'd'"></span>
                </template>
                <template x-if="days > 0 || Number(hours) > 0">
                    <span x-text="hours + 'h'"></span>
                </template>
                <span x-text="minutes + 'm'"></span>
                <span x-text="seconds + 's'"></span>
            </span>
        </template>

    </div>
</div>