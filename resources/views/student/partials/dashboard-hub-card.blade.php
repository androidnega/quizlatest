@props([
    'href',
    'title',
    'subtitle',
    'surface' => 'bg-white',
    'iconWrap' => 'bg-[#667085]',
    'iconColor' => 'text-white',
    'titleColor' => 'text-[#101828]',
    'arrowColor' => 'text-[#667085]',
    'icon' => 'fa-circle',
    'colSpan' => '',
])

<a
    href="{{ $href }}"
    @class([
        'qs-card-hover group flex min-w-0 items-center gap-3 rounded-[1.35rem] border border-transparent p-4 shadow-card lg:gap-4 lg:rounded-[1.5rem] lg:p-6',
        $surface,
        $colSpan,
    ])
>
    <span @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 ease-out group-hover:scale-105 lg:h-12 lg:w-12 lg:rounded-2xl', $iconWrap])>
        <i @class(['fa-solid text-base lg:text-lg', $icon, $iconColor]) aria-hidden="true"></i>
    </span>
    <div class="min-w-0 flex-1">
        <p @class(['truncate text-sm font-semibold lg:text-[15px]', $titleColor])>{{ $title }}</p>
        <p class="mt-0.5 truncate text-xs text-[#667085] lg:text-sm">{{ $subtitle }}</p>
    </div>
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-white ring-1 ring-[#E8EDF3] transition-transform duration-200 ease-out group-hover:translate-x-0.5 lg:h-10 lg:w-10">
        <i @class(['fa-solid fa-arrow-right text-xs lg:text-sm', $arrowColor]) aria-hidden="true"></i>
    </span>
</a>
