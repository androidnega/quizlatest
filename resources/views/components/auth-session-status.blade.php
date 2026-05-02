@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-lg border border-qs-soft bg-qs-card px-4 py-3 text-sm font-medium text-qs-text']) }}>
        {{ $status }}
    </div>
@endif
