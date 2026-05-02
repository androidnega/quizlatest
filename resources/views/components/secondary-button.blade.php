<button {{ $attributes->merge(['type' => 'button', 'class' => 'qs-btn-secondary text-xs font-semibold uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
