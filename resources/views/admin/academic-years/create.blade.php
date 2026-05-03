<x-layouts.admin>
    <x-slot name="title">{{ __('Add academic year') }}</x-slot>
    <x-slot name="subtitle">{{ __('Creates a year with a default “Full year” term (inactive until you activate).') }}</x-slot>

    <div class="qs-surface rounded-lg p-6">
        <form method="POST" action="{{ route('admin.academic-years.store') }}" class="grid gap-5">
            @csrf
            <div>
                <label for="university_id" class="block text-sm font-medium text-qs-text">{{ __('University') }}</label>
                <select id="university_id" name="university_id" required class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg py-2 focus:border-qs-soft focus:ring-qs-accent/40">
                    @foreach ($universities as $u)
                        <option value="{{ $u->id }}" @selected((int) old('university_id') === (int) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('university_id')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="name" class="block text-sm font-medium text-qs-text">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                @error('name')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="start_date" class="block text-sm font-medium text-qs-text">{{ __('Start date') }}</label>
                    <input id="start_date" name="start_date" type="date" required value="{{ old('start_date') }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                    @error('start_date')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-qs-text">{{ __('End date') }}</label>
                    <input id="end_date" name="end_date" type="date" required value="{{ old('end_date') }}" class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg focus:border-qs-soft focus:ring-qs-accent/40" />
                    @error('end_date')
                        <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-qs-text">{{ __('Status label') }}</label>
                <select id="status" name="status" required class="mt-1 block w-full rounded-md border-qs-soft bg-qs-bg py-2 focus:border-qs-soft focus:ring-qs-accent/40">
                    @foreach ([\App\Models\AcademicYear::STATUS_UPCOMING, \App\Models\AcademicYear::STATUS_ACTIVE, \App\Models\AcademicYear::STATUS_CLOSED, \App\Models\AcademicYear::STATUS_ARCHIVED] as $st)
                        <option value="{{ $st }}" @selected(old('status', \App\Models\AcademicYear::STATUS_UPCOMING) === $st)>{{ $st }}</option>
                    @endforeach
                </select>
                @error('status')
                    <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex items-center gap-2">
                <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active') ? 'checked' : '' }} class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
                <label for="is_active" class="text-sm text-qs-muted">{{ __('Set as the active academic year for this university (and activate the default term)') }}</label>
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('admin.academic-years.index') }}" class="inline-flex min-h-[44px] items-center rounded-md border border-qs-soft bg-qs-bg px-4 text-sm text-qs-muted hover:bg-qs-card">{{ __('Cancel') }}</a>
                <button type="submit" class="qs-btn-primary min-h-[44px] px-4 text-sm font-semibold">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</x-layouts.admin>
