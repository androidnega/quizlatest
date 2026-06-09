@props(['lockKey'])

@php
    $locked = $lockStatesByKey[$lockKey] ?? false;
    $titleAttr = $locked ? __('Locked — click to unlock') : __('Unlocked — click to lock');
@endphp

<button
    id="setting-lock-{{ $lockKey }}"
    type="submit"
    form="setting-lock-form-{{ $lockKey }}"
    title="{{ $titleAttr }}"
    aria-label="{{ $titleAttr }}"
    aria-pressed="{{ $locked ? 'true' : 'false' }}"
    class="ml-1 inline-flex h-5 w-5 shrink-0 scroll-mt-24 items-center justify-center rounded text-[10px] transition-colors {{ $locked ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-700' }}"
>
    <i class="fa-solid {{ $locked ? 'fa-lock' : 'fa-lock-open' }}" aria-hidden="true"></i>
    <span class="sr-only">{{ $titleAttr }}</span>
</button>
