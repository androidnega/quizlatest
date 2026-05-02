<button {{ $attributes->merge(['type' => 'submit', 'class' => 'qs-btn-primary text-xs font-semibold uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
