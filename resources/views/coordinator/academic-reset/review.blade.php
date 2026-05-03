<x-layouts.coordinator>
    <x-slot name="title">{{ __('Confirm academic reset') }}</x-slot>
    <x-slot name="subtitle">{{ __('Snapshot') }} #{{ $snapshot->id }} · {{ strtoupper($snapshot->reset_type) }}</x-slot>

    @php($s = $snapshot->summary ?? [])

    <div class="space-y-6">
        <div class="rounded-xl border border-qs-soft bg-qs-card p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Preview summary') }}</h3>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-qs-muted">{{ __('Department') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $s['department_name'] ?? $snapshot->department?->name }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Reset type') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $snapshot->reset_type }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Classes affected') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $s['class_count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Students affected') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $s['student_count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Programs (distinct)') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $s['program_count'] ?? 0 }}</dd>
                </div>
                <div>
                    <dt class="text-qs-muted">{{ __('Levels (distinct)') }}</dt>
                    <dd class="font-medium text-qs-text">{{ $s['level_count'] ?? 0 }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-xl border border-qs-accent/30 bg-qs-accent/10 p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('What will happen') }}</h3>
            <ul class="mt-3 list-disc space-y-2 pl-5 text-sm text-qs-text">
                @foreach (($s['narrative'] ?? []) as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-qs-muted">
                {{ __('Exam sessions, results, proctoring events, and activity logs (except this reset log) are never deleted by this tool.') }}
            </p>
        </div>

        <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
            <h3 class="text-sm font-semibold text-qs-text">{{ __('Apply') }}</h3>
            <p class="mt-2 text-sm text-qs-muted">{{ __('Type RESET and confirm to run changes inside a database transaction.') }}</p>

            <form method="POST" action="{{ route('coordinator.academic-reset.apply', $snapshot) }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="confirmation_phrase" class="block text-xs font-medium text-qs-muted">{{ __('Confirmation phrase') }}</label>
                    <input type="text" name="confirmation_phrase" id="confirmation_phrase" autocomplete="off"
                        class="qs-input mt-1 max-w-xs" placeholder="RESET" required>
                    @error('confirmation_phrase')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <label class="flex items-start gap-2 text-sm text-qs-text">
                    <input type="checkbox" name="confirm_understood" value="1" class="mt-1 rounded border-qs-soft" required>
                    {{ __('I understand this will modify classes and/or student records as summarized above.') }}
                </label>
                @error('confirm_understood')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="qs-btn-primary text-sm">{{ __('Apply reset') }}</button>
                    <a href="{{ route('coordinator.academic-reset.index') }}" class="rounded-lg bg-qs-card px-4 py-2 text-sm text-qs-muted hover:bg-qs-soft">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.coordinator>
