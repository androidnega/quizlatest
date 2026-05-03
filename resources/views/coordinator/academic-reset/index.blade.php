<x-layouts.coordinator>
    <x-slot name="title">{{ __('Academic reset') }}</x-slot>
    <x-slot name="subtitle">{{ __('Department-scoped transitions — preview required before any change') }}</x-slot>

    @if ($departments->isEmpty())
        <p class="text-sm text-qs-muted">{{ __('You have no department assignments.') }}</p>
    @else
        <form method="GET" action="{{ route('coordinator.academic-reset.index') }}" class="mb-8 flex flex-wrap items-end gap-4 rounded-xl border border-qs-soft bg-qs-card p-4">
            <div>
                <label for="department_id" class="block text-xs font-medium text-qs-muted">{{ __('Department') }}</label>
                <select name="department_id" id="department_id" class="qs-input mt-1 min-w-[220px]"
                    onchange="this.form.submit()">
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) $departmentId === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        @php($ownsDept = $departments->contains(fn ($d) => (int) $d->id === (int) $departmentId))

        @if (!$ownsDept || !$departmentId)
            <p class="text-sm text-qs-muted">{{ __('Select a valid department.') }}</p>
        @else
            <div class="rounded-xl border border-qs-soft bg-qs-bg p-5">
                <h3 class="text-sm font-semibold text-qs-text">{{ __('1. Reset type') }}</h3>
                <form method="POST" action="{{ route('coordinator.academic-reset.preview') }}" class="mt-4 space-y-5">
                    @csrf
                    <input type="hidden" name="department_id" value="{{ (int) $departmentId }}">

                    <div class="space-y-2">
                        @foreach ($resetTypes as $value => $label)
                            <label class="flex cursor-pointer items-start gap-2 rounded-lg border border-qs-soft p-3 hover:bg-qs-card">
                                <input type="radio" name="reset_type" value="{{ $value }}" class="mt-1" @checked(old('reset_type', 'complete') === $value)>
                                <span>
                                    <span class="font-medium text-qs-text">{{ strtoupper($value) }}</span>
                                    <span class="mt-0.5 block text-xs text-qs-muted">{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div>
                        <h3 class="text-sm font-semibold text-qs-text">{{ __('2. Filters (partial & continual)') }}</h3>
                        <p class="mt-1 text-xs text-qs-muted">{{ __('For complete or peace reset, filters are ignored. Partial reset requires at least one filter below.') }}</p>

                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Programs') }}</label>
                                <select name="program_ids[]" multiple class="qs-input mt-1 h-32 w-full text-sm" size="5">
                                    @foreach ($programs as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Levels') }}</label>
                                <select name="level_ids[]" multiple class="qs-input mt-1 h-32 w-full text-sm" size="5">
                                    @foreach ($levels as $lv)
                                        <option value="{{ $lv->id }}">{{ $lv->name }} ({{ $lv->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Classes') }}</label>
                                <select name="class_ids[]" multiple class="qs-input mt-1 h-32 w-full text-sm" size="5">
                                    @foreach ($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} · {{ $c->program?->code }} · {{ $c->level?->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="inline-flex items-center gap-2 text-sm text-qs-text">
                                <input type="checkbox" name="promote_class_rows" value="1" class="rounded border-qs-soft" @checked(old('promote_class_rows'))>
                                {{ __('Continual only: also bump class rows to next level when name/program unique allows') }}
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-qs-soft pt-4">
                        <button type="submit" class="qs-btn-primary text-sm">{{ __('Preview impact') }}</button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</x-layouts.coordinator>
