<div class="grid gap-5">
    <div>
        <label for="name" class="block text-sm font-medium qs-heading">Full Name</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $coordinator->name ?? '') }}" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium qs-heading">Email / Username</label>
        <input id="email" name="email" type="text" required value="{{ old('email', $coordinator->email ?? '') }}" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="index_number" class="block text-sm font-medium qs-heading">Index Number (Optional)</label>
        <input id="index_number" name="index_number" type="text" value="{{ old('index_number', $coordinator->index_number ?? '') }}" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('index_number')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-medium qs-heading">{{ isset($coordinator) ? 'Password (Leave blank to keep existing)' : 'Password' }}</label>
        <input id="password" name="password" type="password" {{ isset($coordinator) ? '' : 'required' }} class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('password')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="department_ids" class="block text-sm font-medium qs-heading">Departments (Select one or more)</label>
        <select id="department_ids" name="department_ids[]" multiple required class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white min-h-52">
            @foreach ($faculties as $faculty)
                <optgroup label="{{ $faculty->name }}">
                    @foreach ($faculty->departments as $department)
                        <option value="{{ $department->id }}" {{ in_array($department->id, old('department_ids', $selectedDepartmentIds ?? []), true) ? 'selected' : '' }}>
                            {{ $department->name }}
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-600">Use Ctrl/Cmd + click to select multiple departments.</p>
        @error('department_ids')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('department_ids.*')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-2">
        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $coordinator->is_active ?? true) ? 'checked' : '' }} class="rounded border-[#CFAC81] text-[#CFAC81] focus:ring-[#CFAC81]" />
        <label for="is_active" class="text-sm text-gray-700">Coordinator account is active</label>
    </div>
</div>

<div class="mt-6 flex items-center justify-end gap-3">
    <a href="{{ route('admin.coordinators.index') }}" class="px-4 py-2 rounded-md text-sm border border-[#CFAC81] text-gray-700 bg-white hover:bg-[#EBE6DE]">
        Cancel
    </a>
    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-[#CFAC81] border border-[#CFAC81] hover:bg-[#b9966f]">
        {{ $submitLabel }}
    </button>
</div>
