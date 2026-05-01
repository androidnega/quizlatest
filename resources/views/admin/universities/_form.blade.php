@php
    $settingsValue = old('settings', isset($university) && $university->settings ? json_encode($university->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
@endphp

<div class="grid gap-5">
    <div>
        <label for="name" class="block text-sm font-medium qs-heading">University Name</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $university->name ?? '') }}" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="code" class="block text-sm font-medium qs-heading">Code</label>
        <input id="code" name="code" type="text" value="{{ old('code', $university->code ?? '') }}" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white" />
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="settings" class="block text-sm font-medium qs-heading">Settings (JSON)</label>
        <textarea id="settings" name="settings" rows="8" class="mt-1 block w-full rounded-md border-[#CFAC81] focus:border-[#CFAC81] focus:ring-[#CFAC81] bg-white font-mono text-sm">{{ $settingsValue }}</textarea>
        @error('settings')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-2">
        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $university->is_active ?? true) ? 'checked' : '' }} class="rounded border-[#CFAC81] text-[#CFAC81] focus:ring-[#CFAC81]" />
        <label for="is_active" class="text-sm text-gray-700">University is active</label>
    </div>
</div>

<div class="mt-6 flex items-center justify-end gap-3">
    <a href="{{ route('admin.universities.index') }}" class="px-4 py-2 rounded-md text-sm border border-[#CFAC81] text-gray-700 bg-white hover:bg-[#EBE6DE]">
        Cancel
    </a>
    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-[#CFAC81] border border-[#CFAC81] hover:bg-[#b9966f]">
        {{ $submitLabel }}
    </button>
</div>
