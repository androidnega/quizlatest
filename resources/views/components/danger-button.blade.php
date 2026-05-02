<button {{ $attributes->merge(['type' => 'submit', 'class' => 'qs-btn-danger rounded-md text-xs uppercase tracking-widest']) }}>
    {{ $slot }}
</button>
