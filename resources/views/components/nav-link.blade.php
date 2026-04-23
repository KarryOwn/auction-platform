@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-full bg-brand-soft px-3 py-2 text-sm font-semibold leading-5 text-brand focus:outline-none focus:ring-2 focus:ring-brand/30 transition duration-150 ease-in-out'
            : 'inline-flex items-center rounded-full px-3 py-2 text-sm font-semibold leading-5 text-gray-600 hover:text-brand hover:bg-brand-soft focus:outline-none focus:text-brand focus:bg-brand-soft transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
