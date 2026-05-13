<x-layouts.admin :content-full-width="true">
    <x-slot name="title">{{ __('Create staff account') }}</x-slot>
    <x-slot name="subtitle">{{ __('Add an administrator, coordinator, or examiner. Students are created through the coordinator student directory, not here.') }}</x-slot>

    @php
        $field = 'min-h-[44px] w-full rounded-lg border border-slate-200 bg-white px-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/25';
        $selectMulti = 'min-h-[13rem] w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-500/25';
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-qs-primary hover:underline">{{ __('← Back to manage users') }}</a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" x-data="{ role: @js(old('role', 'admin')) }">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <label for="role" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Role') }}</label>
                    <select
                        id="role"
                        name="role"
                        class="{{ $field }}"
                        x-model="role"
                        required
                    >
                        <option value="admin" @selected(old('role') === 'admin')>{{ __('Admin') }}</option>
                        <option value="coordinator" @selected(old('role') === 'coordinator')>{{ __('Coordinator') }}</option>
                        <option value="examiner" @selected(old('role') === 'examiner')>{{ __('Examiner') }}</option>
                    </select>
                    @error('role')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="name" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required class="{{ $field }}" />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Email / staff username') }}</label>
                    <input id="email" name="email" type="text" value="{{ old('email') }}" required class="{{ $field }}" autocomplete="off" />
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="role === 'coordinator' || role === 'examiner'" x-cloak>
                    <label for="phone" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Phone (optional)') }}</label>
                    <input id="phone" name="phone" type="text" value="{{ old('phone') }}" class="{{ $field }}" autocomplete="tel" />
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="role === 'admin' || role === 'examiner'" x-cloak>
                    <label for="university_id" class="mb-1 block text-xs font-medium text-slate-600">{{ __('University') }}</label>
                    <select id="university_id" name="university_id" class="{{ $field }}" :required="role === 'admin' || role === 'examiner'">
                        <option value="">{{ __('Select university') }}</option>
                        @foreach ($universities as $uni)
                            <option value="{{ $uni->id }}" @selected((string) old('university_id') === (string) $uni->id)>{{ $uni->name }}</option>
                        @endforeach
                    </select>
                    @error('university_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div x-show="role === 'coordinator'" x-cloak>
                    <label for="department_ids" class="mb-1 block text-xs font-medium text-slate-600">{{ __('Departments') }}</label>
                    <select
                        id="department_ids"
                        name="department_ids[]"
                        multiple
                        class="{{ $selectMulti }}"
                        :required="role === 'coordinator'"
                    >
                        @foreach ($faculties as $faculty)
                            <optgroup label="{{ $faculty->name }}">
                                @foreach ($faculty->departments as $department)
                                    <option value="{{ $department->id }}" @selected(in_array($department->id, old('department_ids', []), true))>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Use Ctrl/Cmd + click to select one or more departments. University is taken from the first department.') }}</p>
                    @error('department_ids')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    @error('department_ids.*')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-600">
                    {{ __('Password is auto-generated when the account is created and shown once on the next page.') }}
                </div>

                <div class="flex items-center gap-2">
                    <input id="is_active" name="is_active" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 bg-white text-teal-600 focus:ring-teal-500/30" @checked(old('is_active', true)) />
                    <label for="is_active" class="text-sm text-slate-800">{{ __('Account active') }}</label>
                </div>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <button type="submit" class="qs-btn-primary inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Create account') }}</button>
                <a href="{{ route('admin.users.index') }}" class="qs-btn-secondary inline-flex min-h-[44px] items-center justify-center px-5 text-sm font-semibold">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-layouts.admin>
