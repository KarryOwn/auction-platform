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

    $baseClasses = 'theme-card overflow-hidden';
    
    if ($border) {
        $baseClasses .= ' border border-[var(--color-border)]';
    }

    if ($hover) {
        $baseClasses .= ' theme-card-hover cursor-pointer';
    }
@endphp

<div {{ $attributes->merge(['class' => $baseClasses]) }}>
    @if(isset($header))
        <div class="px-6 py-4 border-b border-[var(--color-border)]">
            {{ $header }}
        </div>
    @endif

    <div class="{{ $paddingClasses }}">
        {{ $slot }}
    </div>

    @if(isset($footer))
        <div class="px-6 py-4 border-t border-[var(--color-border)] bg-surface-tint">
            {{ $footer }}
        </div>
    @endif
</div>
