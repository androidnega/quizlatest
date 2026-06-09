@php
    /**
     * Per-type question count UI, shared between the AI generate and
     * Import JSON paths on the create-assessment screen.
     *
     * Required slot variables:
     * - $idPrefix  unique id prefix for the single-count input (e.g. "ai-count-create", "paste-count-create").
     * - $hiddenName name of the hidden mirror input ('ai_question_count', 'paste_prompt_count').
     *
     * Optional variables:
     * - $emptyMessage  custom warning when no eligible types are selected.
     */
@endphp
<div>
    <template x-if="aiEligibleTypes().length === 0">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs text-amber-900">
            {{ $emptyMessage ?? __('Pick at least one auto-gradable question type (MCQ, True/False, or Fill-in-the-blank) under "Question types in pool" above. Essays are manually written and not part of a generated batch.') }}
        </div>
    </template>

    {{-- Single-type: just one total count field --}}
    <template x-if="aiEligibleTypes().length === 1">
        <div>
            <label class="mb-1 block text-sm font-medium text-qs-muted" for="{{ $idPrefix }}">
                {{ __('Number of questions') }}
                <span class="text-qs-muted/80 text-xs font-normal">(<span x-text="aiTypeLabel(aiEligibleTypes()[0])"></span>)</span>
                <span class="text-qs-danger">*</span>
            </label>
            <input
                id="{{ $idPrefix }}"
                type="number"
                x-model.number="aiTypeCounts[aiEligibleTypes()[0]]"
                min="1"
                max="250"
                required
                class="qs-input mt-1 w-full py-2.5"
            />
            <p class="mt-1 text-xs text-qs-muted">{{ __('Choose any count from 1 to 250. This also sets questions per student for the pool.') }}</p>
        </div>
    </template>

    {{-- Multiple types: per-type count fields --}}
    <template x-if="aiEligibleTypes().length >= 2">
        <div>
            <label class="mb-1 block text-sm font-medium text-qs-muted">{{ __('Questions per type') }} <span class="text-qs-danger">*</span></label>
            <div class="grid grid-cols-2 gap-2 sm:gap-3" :class="aiEligibleTypes().length === 3 ? 'sm:grid-cols-3' : ''">
                <template x-for="t in aiEligibleTypes()" :key="'{{ $idPrefix }}-'+t">
                    <label class="flex items-center gap-2 rounded-lg border border-qs-soft bg-white px-3 py-2 text-sm">
                        <span class="min-w-0 flex-1 text-qs-text" x-text="aiTypeLabel(t)"></span>
                        <input
                            type="number"
                            min="0"
                            max="250"
                            class="qs-input w-20 py-1.5 text-right"
                            x-model.number="aiTypeCounts[t]"
                        />
                    </label>
                </template>
            </div>
            <p class="mt-1 flex items-center justify-between text-xs text-qs-muted">
                <span>{{ __('Total: ') }}<strong class="font-semibold text-qs-text" x-text="aiQuestionCount"></strong> {{ __('questions') }}</span>
                <span x-show="aiQuestionCount === 0" x-cloak class="text-amber-700">{{ __('Set at least 1 question for a type.') }}</span>
            </p>
        </div>
    </template>

    {{-- Hidden mirror so server / clamping logic keeps working --}}
    <input type="hidden" name="{{ $hiddenName }}" :value="aiQuestionCount" />
</div>
