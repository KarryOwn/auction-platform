@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-4 pe-4 py-3 border-l-4 border-brand text-start text-base font-semibold text-brand bg-brand-soft focus:outline-none focus:text-brand focus:bg-brand-soft transition duration-150 ease-in-out'
            : 'block w-full ps-4 pe-4 py-3 border-l-4 border-transparent text-start text-base font-semibold text-gray-600 hover:text-brand hover:bg-brand-soft focus:outline-none focus:text-brand focus:bg-brand-soft transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
