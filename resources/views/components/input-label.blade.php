@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-qs-text']) }}>
    {{ $value ?? $slot }}
</label>
