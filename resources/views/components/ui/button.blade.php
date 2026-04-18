@php
    $baseClasses = 'inline-flex items-center justify-center font-medium focus:outline-none transition-colors';

    $variantClasses = match ($variant) {
        'secondary' => 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50',
        'danger' => 'bg-red-600 text-white hover:bg-red-700',
        'ghost' => 'text-gray-600 hover:bg-gray-100',
        default => 'bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-2 ring-indigo-500',
    };

    $sizeClasses = match ($size) {
        'sm' => 'text-xs px-3 py-1.5 rounded-md gap-1.5',
        'lg' => 'text-base px-6 py-3 rounded-xl gap-2.5',
        default => 'text-sm px-4 py-2 rounded-lg gap-2',
    };

    $stateClasses = '';
    if ($loading) {
        $stateClasses = 'pointer-events-none opacity-75';
    } elseif ($disabled) {
        $stateClasses = 'opacity-50 pointer-events-none';
    }

    $classes = trim("$baseClasses $variantClasses $sizeClasses $stateClasses");
    $isAnchor = !is_null($href);
@endphp

@if($isAnchor)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled) tabindex="-1" aria-disabled="true" @endif @if($loading) aria-busy="true" @endif>
        @if($loading)
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        @elseif($icon)
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $icon }}"></path>
            </svg>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }} @if($disabled || $loading) disabled @endif @if($loading) aria-busy="true" @endif>
        @if($loading)
            <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
            </svg>
        @elseif($icon)
            <svg class="' . ($size === 'sm' ? 'w-4 h-4' : 'w-5 h-5') . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $icon }}"></path>
            </svg>
        @endif
        {{ $slot }}
    </button>
@endif