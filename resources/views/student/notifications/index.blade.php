<x-layouts.student>
    <x-slot name="title">{{ __('Notifications') }}</x-slot>
    <x-slot name="subtitle">{{ __('Timely updates from your assessments and results.') }}</x-slot>

    <div class="mx-auto max-w-2xl space-y-4 pb-6">
        <p class="text-sm text-slate-600">{{ __('These messages are generated from your own activity and schedule. Nothing here is sent by other students.') }}</p>

        @if ($notices === [])
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center text-sm text-slate-600">
                {{ __('You are all caught up — no notices right now.') }}
            </div>
        @else
            <ul class="space-y-3">
                @foreach ($notices as $n)
                    <li class="rounded-xl border border-slate-200 bg-white p-4">
                        <p class="text-sm font-semibold text-slate-900">{{ $n['title'] }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $n['body'] }}</p>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            <time class="text-xs text-slate-400" datetime="{{ $n['at'] }}">
                                {{ \Illuminate\Support\Carbon::parse($n['at'])->timezone(config('app.timezone'))->format('M j, Y · H:i') }}
                            </time>
                            @if (! empty($n['href']))
                                <a href="{{ $n['href'] }}" class="inline-flex min-h-[44px] items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                                    {{ __('Open') }}
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.student>
