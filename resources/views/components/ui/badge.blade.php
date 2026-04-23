@props([
    'color' => 'gray',
    'size' => 'sm',
    'dot' => false,
    'pulse' => false,
])

@php
    $colorClasses = match ($color) {
        'green' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-300',
        'indigo' => 'bg-brand-soft text-brand dark:bg-green-900 dark:text-green-300',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
        default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    };

    $dotColorClasses = match ($color) {
        'green' => 'bg-green-500',
        'red' => 'bg-red-500',
        'blue' => 'bg-blue-500',
        'amber' => 'bg-amber-500',
        'indigo' => 'bg-indigo-500',
        'orange' => 'bg-orange-500',
        default => 'bg-gray-500',
    };

    $sizeClasses = match ($size) {
        'xs' => 'text-[0.625rem] px-2 py-0.5',
        default => 'text-xs px-2.5 py-0.5',
    };

    $classes = "inline-flex items-center font-bold rounded-full {$sizeClasses} {$colorClasses}";
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    @if ($dot)
        <span class="flex items-center justify-center mr-1.5 h-1.5 w-1.5 {{ $pulse ? 'relative' : '' }}">
            @if ($pulse)
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75 {{ $dotColorClasses }}"></span>
            @endif
            <span class="relative inline-flex rounded-full h-1.5 w-1.5 {{ $dotColorClasses }}"></span>
        </span>
    @endif
    {{ $slot }}
</span>
