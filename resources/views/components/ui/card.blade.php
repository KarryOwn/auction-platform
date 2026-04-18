@props([
    'padding' => 'md',
    'hover' => false,
    'border' => true,
])

@php
    $paddingClasses = match($padding) {
        'none' => 'p-0',
        'sm' => 'p-4',
        'lg' => 'p-8',
        default => 'p-6',
    };

    $baseClasses = 'bg-white dark:bg-gray-800 rounded-xl overflow-hidden';
    
    if ($border) {
        $baseClasses .= ' border border-gray-200 dark:border-gray-700';
    }

    if ($hover) {
        $baseClasses .= ' hover:shadow-md transition-shadow duration-200 cursor-pointer';
    }
@endphp

<div {{ $attributes->merge(['class' => $baseClasses]) }}>
    @if(isset($header))
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            {{ $header }}
        </div>
    @endif

    <div class="{{ $paddingClasses }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
            {{ $footer }}
        </div>
    @endif
</div>