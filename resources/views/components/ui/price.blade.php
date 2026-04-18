@props([
    'amount',
    'currency' => 'USD',
    'size' => 'md',
    'label' => null,
    'animate' => false,
    'id' => null,
])

@php
    $symbol = match (strtoupper($currency)) {
        'EUR' => '€',
        'GBP' => '£',
        default => '$',
    };

    $formattedAmount = number_format((float) $amount, 2);

    $sizeClasses = match ($size) {
        'sm' => 'text-base font-semibold',
        'lg' => 'text-4xl font-black',
        'xl' => 'text-6xl font-black tabular-nums',
        default => 'text-2xl font-bold',
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col']) }} @if($id) id="{{ $id }}" @endif>
    @if($label)
        <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-0.5">{{ $label }}</span>
    @endif

    <div class="{{ $sizeClasses }} @if(!$animate) text-green-600 @endif"
         @if($animate)
            x-data="{ flash: false, id: {{ $id ? "'{$id}'" : 'null' }} }"
            x-init="window.addEventListener('price-updated', (e) => { if (!id || e.detail.id === id) { flash = true; setTimeout(() => flash = false, 600) } })"
            :class="flash ? 'text-amber-500 scale-105 transition-all' : 'text-green-600 transition-all'"
         @endif>
        {{ $symbol }}{{ $formattedAmount }}
    </div>
</div>
