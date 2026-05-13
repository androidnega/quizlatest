<x-layouts.examiner>
    <x-slot name="title">Grade essay</x-slot>
    <x-slot name="subtitle">{{ $answer->examSession?->exam?->title }}</x-slot>

    @php
        $md = $answer->question->metadata ?? [];
        $history = ($answer->evaluation_detail['grading_history'] ?? []) ?: [];
        $isOverride = $answer->evaluation_status === 'manual_graded';
    @endphp

    <div class="max-w-3xl space-y-6">
        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Student') }}</p>
            <p class="mt-1 font-medium text-qs-text">{{ $answer->examSession?->student?->name }}</p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">Question (max {{ $answer->question->marks }} marks)</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->question->question_text }}</p>
            @if(!empty($md['topic']) || !empty($md['difficulty']) || !empty($md['learning_outcome']))
                <dl class="mt-4 grid gap-2 text-xs text-qs-muted sm:grid-cols-2">
                    @if(!empty($md['topic']))
                        <div><span class="font-semibold text-qs-text">{{ __('Topic') }}:</span> {{ $md['topic'] }}</div>
                    @endif
                    @if(!empty($md['difficulty']))
                        <div><span class="font-semibold text-qs-text">{{ __('Difficulty') }}:</span> {{ $md['difficulty'] }}</div>
                    @endif
                    @if(!empty($md['learning_outcome']))
                        <div class="sm:col-span-2"><span class="font-semibold text-qs-text">{{ __('Learning outcome') }}:</span> {{ $md['learning_outcome'] }}</div>
                    @endif
                </dl>
            @endif
        </div>

        @if(!empty($md['marking_guide']) || !empty($md['sample_answer']) || !empty($md['rubric']) || !empty($md['explanation']))
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm space-y-4">
                <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Grading reference') }}</p>
                @if(!empty($md['marking_guide']))
                    <div>
                        <p class="text-xs font-medium text-qs-muted">{{ __('Marking guide') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-qs-text">{{ $md['marking_guide'] }}</p>
                    </div>
                @endif
                @if(!empty($md['rubric']))
                    <div>
                        <p class="text-xs font-medium text-qs-muted">{{ __('Rubric') }}</p>
                        @if(is_array($md['rubric']))
                            <pre class="mt-1 overflow-x-auto rounded-lg bg-qs-soft/40 p-3 text-xs text-qs-text">{{ json_encode($md['rubric'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        @else
                            <p class="mt-1 whitespace-pre-wrap text-qs-text">{{ $md['rubric'] }}</p>
                        @endif
                    </div>
                @endif
                @if(!empty($md['sample_answer']))
                    <div>
                        <p class="text-xs font-medium text-qs-muted">{{ __('Sample answer') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-qs-text">{{ $md['sample_answer'] }}</p>
                    </div>
                @endif
                @if(!empty($md['explanation']))
                    <div>
                        <p class="text-xs font-medium text-qs-muted">{{ __('Explanation') }}</p>
                        <p class="mt-1 whitespace-pre-wrap text-qs-text">{{ $md['explanation'] }}</p>
                    </div>
                @endif
            </div>
        @endif

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
            <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Submitted answer') }}</p>
            <p class="mt-2 whitespace-pre-wrap text-qs-text">{{ $answer->answer_payload['text'] ?? '' }}</p>
        </div>

        @if($isOverride && count($history) > 0)
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5 text-sm">
                <p class="text-xs font-semibold uppercase text-qs-muted">{{ __('Grading history') }}</p>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-qs-text">
                    @foreach($history as $row)
                        <li class="text-xs sm:text-sm">
                            {{ ($row['graded_at'] ?? '') }} —
                            {{ ($row['points_awarded'] ?? '') }} {{ __('pts') }}
                            @if(!empty($row['override_reason']))
                                ({{ __('override') }}: {{ $row['override_reason'] }})
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="post" action="{{ route('examiner.grading.grade', $answer) }}" class="space-y-4 rounded-xl border border-qs-soft bg-qs-bg p-5">
            @csrf
            @if($isOverride)
                <p class="text-sm text-qs-muted">{{ __('You are updating an existing grade. A short reason is required for the audit trail.') }}</p>
            @endif
            <div>
                <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Points') }}</label>
                <input type="number" name="points_awarded" step="0.01" min="0" max="{{ $answer->question->marks }}" required
                    class="qs-input mt-0 w-full max-w-xs py-2.5 sm:w-40" value="{{ old('points_awarded', $answer->points_awarded ?? 0) }}" />
                @error('points_awarded')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Feedback (optional)') }}</label>
                <textarea name="grader_feedback" rows="4" class="qs-input mt-0 py-2.5">{{ old('grader_feedback', $answer->grader_feedback) }}</textarea>
            </div>
            @if($isOverride)
                <div>
                    <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Reason for change') }} <span class="text-qs-danger">*</span></label>
                    <textarea name="override_reason" rows="3" required class="qs-input mt-0 py-2.5" placeholder="{{ __('Explain why the mark is being changed.') }}">{{ old('override_reason') }}</textarea>
                    @error('override_reason')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
            @endif
            <div class="flex flex-col gap-3 sm:flex-row">
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Save grade') }}</button>
                <a href="{{ route('examiner.grading.pending') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-layouts.examiner>
