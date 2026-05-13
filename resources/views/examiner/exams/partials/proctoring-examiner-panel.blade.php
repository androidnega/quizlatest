@props([
    'proctoringPolicy',
    'examProctoringControls',
    'variant' => 'form',
    'formAction' => null,
    'submitLabel' => null,
    'contained' => false,
])

@php
    $v = (string) $variant;
    $isEmbedded = $v === 'embedded';
    $isReadonly = $v === 'readonly';
    $isForm = $v === 'form';
    $contained = filter_var($contained, FILTER_VALIDATE_BOOLEAN);
    $showOuterCard = ! $isEmbedded && ! $contained;
@endphp

<section
    @class([
        'rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6' => $showOuterCard,
        'mt-6 border-t border-qs-soft pt-6' => $contained && ! $isEmbedded,
    ])
    aria-labelledby="proctoring-examiner-panel-heading"
>
    <h2 id="proctoring-examiner-panel-heading" class="text-sm font-semibold text-slate-900">{{ __('Proctoring options') }}</h2>
    <p class="mt-1 text-xs text-slate-600">{{ __('Capped by your institution’s policy.') }}</p>

    <div class="mt-4 space-y-2">
        <label class="flex min-h-[44px] cursor-default items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-800">
            <input type="checkbox" class="size-4 rounded border-slate-300 text-sky-600" @checked($proctoringPolicy['allow_exam_start_snapshot']) disabled />
            <span class="min-w-0 flex-1">{{ __('Exam start verification photo') }}</span>
            <span class="shrink-0 text-[11px] font-medium text-slate-500">{{ $proctoringPolicy['allow_exam_start_snapshot'] ? __('Required by admin') : __('Disabled by admin') }}</span>
        </label>
        <label class="flex min-h-[44px] cursor-default items-center gap-3 rounded-lg border border-slate-200 bg-slate-50/80 px-3 py-2 text-sm text-slate-800">
            <input type="checkbox" class="size-4 rounded border-slate-300 text-sky-600" @checked($proctoringPolicy['allow_camera_monitoring']) disabled />
            <span class="min-w-0 flex-1">{{ __('Proctoring camera during exam') }}</span>
            <span class="shrink-0 text-[11px] font-medium text-slate-500">{{ $proctoringPolicy['allow_camera_monitoring'] ? __('Required by admin') : __('Disabled by admin') }}</span>
        </label>
    </div>

    @if ($isReadonly)
        <div class="mt-5 space-y-2 border-t border-slate-100 pt-5 text-sm text-slate-600">
            <p><span class="font-medium text-slate-800">{{ __('Phone detection') }}:</span> {{ $examProctoringControls['phone_detection_enabled'] ? __('On') : __('Off') }}</p>
            <p><span class="font-medium text-slate-800">{{ __('Fullscreen enforcement') }}:</span> {{ $examProctoringControls['fullscreen_enforced'] ? __('On') : __('Off') }}</p>
            <p><span class="font-medium text-slate-800">{{ __('Auto submit on severe violations') }}:</span> {{ $examProctoringControls['auto_submit_enabled'] ? __('On') : __('Off') }}</p>
        </div>
    @elseif ($isForm)
        <form method="post" action="{{ $formAction }}" class="mt-5 space-y-2 border-t border-slate-100 pt-5">
            @csrf
            @method('patch')
            <p class="mb-2 text-xs text-slate-600">{{ __('These apply when students take the exam.') }}</p>
            <input type="hidden" name="enable_phone" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_phone" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_phone', $examProctoringControls['phone_detection_enabled'])) @disabled(! $proctoringPolicy['allow_phone']) />
                <span>{{ __('Phone detection') }}</span>
            </label>
            <input type="hidden" name="enable_fullscreen" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_fullscreen" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_fullscreen', $examProctoringControls['fullscreen_enforced'])) @disabled(! $proctoringPolicy['allow_fullscreen']) />
                <span>{{ __('Fullscreen enforcement') }}</span>
            </label>
            <input type="hidden" name="enable_auto_submit" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_auto_submit" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_auto_submit', $examProctoringControls['auto_submit_enabled'])) @disabled(! $proctoringPolicy['allow_auto_submit']) />
                <span>{{ __('Auto submit on severe violations') }}</span>
            </label>
            <div class="pt-2">
                <button type="submit" class="rounded-lg bg-sky-600 px-3 py-2 text-xs font-semibold text-white hover:bg-sky-700">{{ $submitLabel ?? __('Save proctoring options') }}</button>
            </div>
        </form>
    @else
        {{-- embedded: parent <form> submits create assessment --}}
        <div class="mt-5 space-y-2 border-t border-slate-100 pt-5">
            <p class="mb-2 text-xs text-slate-600">{{ __('These apply when students take the exam.') }}</p>
            <input type="hidden" name="enable_phone" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_phone" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_phone', $examProctoringControls['phone_detection_enabled'])) @disabled(! $proctoringPolicy['allow_phone']) />
                <span>{{ __('Phone detection') }}</span>
            </label>
            <input type="hidden" name="enable_fullscreen" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_fullscreen" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_fullscreen', $examProctoringControls['fullscreen_enforced'])) @disabled(! $proctoringPolicy['allow_fullscreen']) />
                <span>{{ __('Fullscreen enforcement') }}</span>
            </label>
            <input type="hidden" name="enable_auto_submit" value="0" />
            <label class="flex min-h-[44px] cursor-pointer items-center gap-3 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 hover:bg-slate-50">
                <input type="checkbox" name="enable_auto_submit" value="1" class="size-4 rounded border-slate-300 text-sky-600" @checked(old('enable_auto_submit', $examProctoringControls['auto_submit_enabled'])) @disabled(! $proctoringPolicy['allow_auto_submit']) />
                <span>{{ __('Auto submit on severe violations') }}</span>
            </label>
        </div>
    @endif
</section>
