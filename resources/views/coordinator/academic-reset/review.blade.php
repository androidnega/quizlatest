<x-layouts.coordinator>
    <x-slot name="title">{{ __('Confirm academic reset') }}</x-slot>
    <x-slot name="subtitle">{{ __('Snapshot') }} #{{ $snapshot->id }} · {{ strtoupper($snapshot->reset_type) }}</x-slot>

    @php($s = $snapshot->summary ?? [])

    <div class="space-y-6">
        <div id="academic-reset-summary" class="scroll-mt-24 rounded-xl border border-qs-soft bg-qs-card p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Preview summary') }}</h3>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Department') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['department_name'] ?? $snapshot->department?->name }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Academic year') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['academic_year_name'] ?? $snapshot->academicYear?->name ?? '—' }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Reset type') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $snapshot->reset_type }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Classes affected') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['class_count'] ?? 0 }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Students affected') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['student_count'] ?? 0 }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Programs (distinct)') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['program_count'] ?? 0 }}</dd>
                </div>
                <div class="rounded-lg border border-qs-soft bg-qs-bg px-3 py-2">
                    <dt class="text-qs-muted">{{ __('Levels (distinct)') }}</dt>
                    <dd class="mt-0.5 font-medium text-qs-text">{{ $s['level_count'] ?? 0 }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-qs-accent/30 bg-qs-accent/10 p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('What will happen') }}</h3>
            <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-qs-text">
                @foreach (($s['narrative'] ?? []) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-qs-muted">
                {{ __('Exam sessions, results, proctoring events, and activity logs (except this reset log) are never deleted by this tool.') }}
            </p>
        </div>

        <div id="academic-reset-apply" class="scroll-mt-24 rounded-xl border border-qs-soft bg-qs-bg p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Apply') }}</h3>
            <p class="mt-2 text-sm text-qs-muted">{{ __('Type RESET and confirm to run changes inside a database transaction.') }}</p>

            <form method="POST" action="{{ route('coordinator.academic-reset.apply', $snapshot) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="confirmation_phrase" class="block text-xs font-medium text-qs-muted">{{ __('Confirmation phrase') }}</label>
                    <input type="text" name="confirmation_phrase" id="confirmation_phrase" autocomplete="off"
                        class="qs-input mt-2 min-h-[44px] w-full max-w-full py-2.5 sm:max-w-xs" placeholder="RESET" required>
                    @error('confirmation_phrase')
                        <p class="mt-1 text-xs text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex min-h-[44px] cursor-pointer items-start gap-3 text-sm text-qs-text">
                    <input type="checkbox" name="confirm_understood" value="1" class="mt-1 size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" required>
                    {{ __('I understand this will modify classes and/or student records as summarized above.') }}
                </label>
                @error('confirm_understood')
                    <p class="text-xs text-qs-danger">{{ $message }}</p>
                @enderror

                <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:flex-wrap">
                    <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Apply reset') }}</button>
                    <a href="{{ route('coordinator.academic-reset.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-4 text-sm font-semibold">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.coordinator>
