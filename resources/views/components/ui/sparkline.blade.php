@props([
    'values' => [],
    /** sky | rose | amber | violet | teal | blue | slate */
    'tone' => 'slate',
    /** Animate reveal on load (coordinator overview metrics). */
    'animate' => true,
])

@php
    $toneStroke = match ($tone) {
        'sky' => 'text-sky-400',
        'rose' => 'text-rose-400',
        'amber' => 'text-amber-400',
        'violet' => 'text-violet-400',
        'teal' => 'text-teal-400',
        'blue' => 'text-blue-400',
        default => 'text-slate-400',
    };

    $vals = array_values(array_map(static fn ($v) => max(0.0, (float) $v), $values));
    if ($vals === []) {
        $vals = [0.0];
    }

    $max = max($vals);
    if ($max <= 0) {
        $max = 1.0;
    }

    $w = 128;
    $h = 40;
    $padX = 2;
    $padY = 4;
    $n = count($vals);

    $pts = [];
    $areaPts = ["0,$h"];

    foreach ($vals as $i => $v) {
        $x = $n <= 1 ? $w / 2 : $padX + ($i / ($n - 1)) * ($w - 2 * $padX);
        $y = $padY + (1 - ($v / $max)) * ($h - 2 * $padY);
        $sx = round($x, 2);
        $sy = round($y, 2);
        $pts[] = "{$sx},{$sy}";
        $areaPts[] = "{$sx},{$sy}";
    }

    $areaPts[] = "{$w},{$h}";
@endphp

<div {{ $attributes->merge(['class' => 'qs-sparkline-inner min-h-[2.25rem] w-full '.$toneStroke]) }}>
    @if (filter_var($animate, FILTER_VALIDATE_BOOLEAN))
        <div class="qs-sparkline-reveal">
            <svg
                viewBox="0 0 {{ $w }} {{ $h }}"
                class="h-9 w-full"
                preserveAspectRatio="none"
                aria-hidden="true"
            >
                <polygon
                    fill="currentColor"
                    class="opacity-[0.14]"
                    points="{{ implode(' ', $areaPts) }}"
                />
                <polyline
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    vector-effect="non-scaling-stroke"
                    points="{{ implode(' ', $pts) }}"
                />
            </svg>
        </div>
    @else
        <svg
            viewBox="0 0 {{ $w }} {{ $h }}"
            class="h-9 w-full"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <polygon
                fill="currentColor"
                class="opacity-[0.14]"
                points="{{ implode(' ', $areaPts) }}"
            />
            <polyline
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                vector-effect="non-scaling-stroke"
                points="{{ implode(' ', $pts) }}"
            />
        </svg>
    @endif
</div>
