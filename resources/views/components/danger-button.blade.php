<button {{ $attributes->merge(['type' => 'submit', 'class' => 'theme-button theme-button-danger text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
