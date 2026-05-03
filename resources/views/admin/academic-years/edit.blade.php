<x-layouts.admin>
    <x-slot name="title">{{ __('Edit academic year') }}</x-slot>
    <x-slot name="subtitle">{{ $academicYear->name }} · {{ $academicYear->university?->name }}</x-slot>

    <div class="qs-surface mb-8 rounded-lg p-6">
        <form method="POST" action="{{ route('admin.academic-years.update', $academicYear) }}" class="grid gap-5">
            @csrf
            @method('PUT')
            <div>
                <label for="university_id" class="block text-sm font-medium text-qs-text">{{ __('University') }}</label>
                <select id="university_id" name="university_id" required class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg py-2 focus:border-qs-soft focus:ring-qs-accent/40">
                    @foreach ($universities as $u)
                        <option value="{{ $u->id }}" @selected((int) old('university_id', $academicYear->university_id) === (int) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('university_id')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-medium text-qs-text">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" required value="{{ old('name', $academicYear->name) }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                @error('name')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-qs-text">{{ __('Start date') }}</label>
                    <input id="start_date" name="start_date" type="date" required value="{{ old('start_date', $academicYear->start_date?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                    @error('start_date')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-qs-text">{{ __('End date') }}</label>
                    <input id="end_date" name="end_date" type="date" required value="{{ old('end_date', $academicYear->end_date?->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                    @error('end_date')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-qs-text">{{ __('Status label') }}</label>
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg py-2 focus:border-qs-soft focus:ring-qs-accent/40">
                    @foreach ([\App\Models\AcademicYear::STATUS_UPCOMING, \App\Models\AcademicYear::STATUS_ACTIVE, \App\Models\AcademicYear::STATUS_CLOSED, \App\Models\AcademicYear::STATUS_ARCHIVED] as $st)
                        <option value="{{ $st }}" @selected(old('status', $academicYear->status) === $st)>{{ $st }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $academicYear->is_active) ? 'checked' : '' }} class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                <label for="is_active" class="text-sm text-qs-muted">{{ __('Active academic year for this university') }}</label>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.academic-years.index') }}" class="inline-flex min-h-[44px] items-center rounded-md border border-qs-soft bg-qs-bg px-4 text-sm text-qs-muted hover:bg-qs-card">{{ __('Back') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Update year') }}</button>
            </div>
        </form>
    </div>

    <div class="rounded-lg border border-qs-soft bg-qs-card p-6">
        <h3 class="text-sm font-semibold text-qs-text">{{ __('Terms') }}</h3>
        <p class="mt-1 text-xs text-qs-muted">{{ __('Only one term may be active per year. Activating a term updates exam-period defaults for that year.') }}</p>

        <div class="mt-6 space-y-8">
            @foreach ($academicYear->terms as $term)
                <div class="rounded-lg border border-qs-soft bg-qs-bg p-4">
                    <form method="POST" action="{{ route('admin.academic-years.terms.update', [$academicYear, $term]) }}" class="grid gap-4">
                        @csrf
                        @method('PUT')
                        <p class="text-xs font-medium text-qs-muted">{{ __('Term') }} #{{ $term->id }}</p>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Name') }}</label>
                                <input name="name" type="text" required value="{{ old('name', $term->name) }}" class="qs-input mt-1 w-full py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Start') }}</label>
                                <input name="start_date" type="date" required value="{{ old('start_date', $term->start_date?->format('Y-m-d')) }}" class="qs-input mt-1 w-full py-2 text-sm" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('End') }}</label>
                                <input name="end_date" type="date" required value="{{ old('end_date', $term->end_date?->format('Y-m-d')) }}" class="qs-input mt-1 w-full py-2 text-sm" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label class="block text-xs font-medium text-qs-muted">{{ __('Status') }}</label>
                                <select name="status" class="qs-input mt-1 min-h-[44px] py-2 text-sm">
                                    @foreach ([\App\Models\Term::STATUS_UPCOMING, \App\Models\Term::STATUS_ACTIVE, \App\Models\Term::STATUS_CLOSED, \App\Models\Term::STATUS_ARCHIVED] as $tst)
                                        <option value="{{ $tst }}" @selected(old('status', $term->status) === $tst)>{{ $tst }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <label class="flex items-center gap-2 pt-6 text-sm text-qs-text">
                                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $term->is_active) ? 'checked' : '' }} class="size-4 rounded border-qs-soft text-qs-accent" />
                                {{ __('Active term') }}
                            </label>
                            <button type="submit" class="qs-btn-secondary ms-auto min-h-[44px] px-4 text-sm font-semibold">{{ __('Save term') }}</button>
                        </div>
                    </form>
                </div>
            @endforeach

            <div class="rounded-lg border border-dashed border-qs-soft bg-qs-bg p-4">
                <h4 class="text-sm font-medium text-qs-text">{{ __('Add term') }}</h4>
                <form method="POST" action="{{ route('admin.academic-years.terms.store', $academicYear) }}" class="mt-4 grid gap-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Name') }}</label>
                            <input name="name" type="text" required value="{{ old('name') }}" placeholder="{{ __('e.g. Semester 2') }}" class="qs-input mt-1 w-full py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Start') }}</label>
                            <input name="start_date" type="date" required value="{{ old('start_date') }}" class="qs-input mt-1 w-full py-2 text-sm" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-qs-muted">{{ __('End') }}</label>
                            <input name="end_date" type="date" required value="{{ old('end_date') }}" class="qs-input mt-1 w-full py-2 text-sm" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-medium text-qs-muted">{{ __('Status') }}</label>
                            <select name="status" class="qs-input mt-1 min-h-[44px] py-2 text-sm">
                                @foreach ([\App\Models\Term::STATUS_UPCOMING, \App\Models\Term::STATUS_ACTIVE, \App\Models\Term::STATUS_CLOSED, \App\Models\Term::STATUS_ARCHIVED] as $tst)
                                    <option value="{{ $tst }}" @selected(old('status', \App\Models\Term::STATUS_UPCOMING) === $tst)>{{ $tst }}</option>
                                @endforeach
                            </select>
                        </div>
                        <label class="flex items-center gap-2 pt-6 text-sm text-qs-text">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active') ? 'checked' : '' }} class="size-4 rounded border-qs-soft text-qs-accent" />
                            {{ __('Active term') }}
                        </label>
                        <button type="submit" class="qs-btn-primary ms-auto min-h-[44px] px-4 text-sm font-semibold">{{ __('Add term') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
