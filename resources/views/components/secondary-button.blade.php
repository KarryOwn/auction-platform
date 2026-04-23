<button {{ $attributes->merge(['type' => 'button', 'class' => 'theme-button theme-button-secondary text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
