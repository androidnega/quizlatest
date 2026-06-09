@props([
    'href',
    'label',
    'count' => null,
    'primary' => false,
])

<a
    href="{{ $href }}"
    @class([
        'inline-flex min-h-[40px] max-w-full min-w-0 items-center gap-1.5 rounded-lg border px-3.5 text-sm font-semibold transition-colors',
        $primary
            ? 'border-transparent bg-[#EF3340] text-white hover:bg-[#D91F2D]'
            : 'border-slate-200 bg-white text-slate-800 hover:bg-slate-50',
    ])
>
    <span class="min-w-0 truncate">{{ $label }}</span>
    @if ($count !== null && (int) $count > 0)
        <span @class([
            'inline-flex min-h-[1.25rem] min-w-[1.25rem] shrink-0 items-center justify-center rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums leading-none ring-1',
            $primary ? 'bg-white/20 text-white ring-white/30' : 'bg-sky-100 text-sky-900 ring-sky-300/60',
        ])>{{ $count }}</span>
    @endif
</a>
