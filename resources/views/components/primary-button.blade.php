<button {{ $attributes->merge(['type' => 'submit', 'class' => 'theme-button theme-button-primary text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
