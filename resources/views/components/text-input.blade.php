@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'qs-input disabled:cursor-not-allowed disabled:opacity-60']) }}>
