<x-layouts.coordinator>
    <x-slot name="title">{{ __('Academic reset') }}</x-slot>
    <x-slot name="subtitle">{{ __('Department-scoped transitions — preview required before any change') }}</x-slot>

    @if ($departments->isEmpty())
        <p class="text-sm text-qs-muted">{{ __('You have no department assignments.') }}</p>
    @else
        <form method="GET" action="{{ route('coordinator.academic-reset.index') }}" class="mb-8 rounded-xl border border-qs-soft bg-qs-card p-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="department_id" class="block text-xs font-medium text-qs-muted">{{ __('Department') }}</label>
                    <select name="department_id" id="department_id" class="qs-input mt-2 min-h-[44px] w-full min-w-0 py-2.5"
                        onchange="this.form.submit()">
                        @foreach ($departments as $d)
                            <option value="{{ $d->id }}" @selected((int) $departmentId === (int) $d->id)>{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($academicYears->isNotEmpty())
                    <div>
                        <label for="academic_year_id" class="block text-xs font-medium text-qs-muted">{{ __('Academic year (scope)') }}</label>
                        <select name="academic_year_id" id="academic_year_id" class="qs-input mt-2 min-h-[44px] w-full min-w-0 py-2.5"
                            onchange="this.form.submit()">
                            @foreach ($academicYears as $ay)
                                <option value="{{ $ay->id }}" @selected((int) ($scopedAcademicYearId ?? 0) === (int) $ay->id)>
                                    {{ $ay->name }}{{ $ay->is_active ? ' · '.__('Active') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </form>

        @php($ownsDept = $departments->contains(fn ($d) => (int) $d->id === (int) $departmentId))

        @if ($academicYears->isEmpty())
            <p class="text-sm text-qs-muted">{{ __('No academic years are configured for your university. Ask an administrator to add one before running a reset.') }}</p>
        @elseif (!$ownsDept || !$departmentId)
            <p class="text-sm text-qs-muted">{{ __('Select a valid department.') }}</p>
        @else
            <div id="academic-reset-type" class="scroll-mt-24 rounded-xl border border-qs-soft bg-qs-bg p-5">
                <h3 class="text-sm font-semibold text-qs-text">{{ __('1. Reset type') }}</h3>
                <form method="POST" action="{{ route('coordinator.academic-reset.preview') }}" class="mt-4 space-y-5">
                    @csrf
                    <input type="hidden" name="department_id" value="{{ (int) $departmentId }}">
                    <input type="hidden" name="academic_year_id" value="{{ (int) $scopedAcademicYearId }}">

                    <div class="space-y-2">
                        @foreach ($resetTypes as $value => $label)
                            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 rounded-lg border border-qs-soft p-3 hover:bg-qs-card">
                                <input type="radio" name="reset_type" value="{{ $value }}" class="mt-1.5 size-4 shrink-0 border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('reset_type', 'complete') === $value)>
                                <span class="min-w-0">
                                    <span class="font-medium text-qs-text">{{ strtoupper($value) }}</span>
                                    <span class="mt-0.5 block text-xs text-qs-muted">{{ $label }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div id="academic-reset-filters" class="scroll-mt-24">
                        <h3 class="text-sm font-semibold text-qs-text">{{ __('2. Filters (partial & continual)') }}</h3>
                        <p class="mt-1 text-xs text-qs-muted">{{ __('All reset types use the academic year selected above. For complete or peace reset, program/level/class filters are ignored. Partial reset requires at least one filter below.') }}</p>

                        <div class="mt-3 grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Programs') }}</label>
                                <p class="mt-0.5 text-[11px] text-qs-muted">{{ __('Tap or Ctrl/Cmd-click to select multiple.') }}</p>
                                <select name="program_ids[]" multiple class="qs-input mt-2 min-h-[12rem] w-full py-2 text-sm" size="6">
                                    @foreach ($programs as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Levels') }}</label>
                                <p class="mt-0.5 text-[11px] text-qs-muted">{{ __('Tap or Ctrl/Cmd-click to select multiple.') }}</p>
                                <select name="level_ids[]" multiple class="qs-input mt-2 min-h-[12rem] w-full py-2 text-sm" size="6">
                                    @foreach ($levels as $lv)
                                        <option value="{{ $lv->id }}">{{ $lv->name }} ({{ $lv->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Classes') }}</label>
                                <p class="mt-0.5 text-[11px] text-qs-muted">{{ __('Tap or Ctrl/Cmd-click to select multiple.') }}</p>
                                <select name="class_ids[]" multiple class="qs-input mt-2 min-h-[12rem] w-full py-2 text-sm" size="6">
                                    @foreach ($classes as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }} · {{ $c->program?->code }} · {{ $c->level?->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="flex min-h-[44px] cursor-pointer items-start gap-3 text-sm text-qs-text">
                                <input type="checkbox" name="promote_class_rows" value="1" class="mt-1 size-4 shrink-0 rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" @checked(old('promote_class_rows'))>
                                <span>{{ __('Continual only: also bump class rows to next level when name/program unique allows') }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-qs-soft pt-4">
                        <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Preview impact') }}</button>
                    </div>
                </form>
            </div>
        @endif
    @endif
</x-layouts.coordinator>
