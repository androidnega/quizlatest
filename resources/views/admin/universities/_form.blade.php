@php
    $settingsValue = old('settings', isset($university) && $university->settings ? json_encode($university->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '');
@endphp

<div class="grid gap-5">
    <div>
        <label for="name" class="block text-sm font-medium qs-heading">University Name</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $university->name ?? '') }}" class="mt-1 block w-full rounded-md border-qs-soft focus:border-qs-soft focus:ring-qs-accent/40 bg-qs-bg" />
        @error('name')
            <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="code" class="block text-sm font-medium qs-heading">Code</label>
        <input id="code" name="code" type="text" value="{{ old('code', $university->code ?? '') }}" class="mt-1 block w-full rounded-md border-qs-soft focus:border-qs-soft focus:ring-qs-accent/40 bg-qs-bg" />
        @error('code')
            <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="settings" class="block text-sm font-medium qs-heading">Settings (JSON)</label>
        <textarea id="settings" name="settings" rows="8" class="mt-1 block w-full rounded-md border-qs-soft focus:border-qs-soft focus:ring-qs-accent/40 bg-qs-bg font-mono text-sm">{{ $settingsValue }}</textarea>
        @error('settings')
            <p class="mt-1 text-sm text-qs-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-2">
        <input id="is_active" name="is_active" type="checkbox" value="1" {{ old('is_active', $university->is_active ?? true) ? 'checked' : '' }} class="rounded border-qs-soft text-qs-accent focus:ring-qs-accent/40" />
        <label for="is_active" class="text-sm text-qs-muted">University is active</label>
    </div>
</div>

<div class="mt-6 flex items-center justify-end gap-3">
    <a href="{{ route('admin.universities.index') }}" class="px-4 py-2 rounded-md text-sm border border-qs-soft text-qs-muted bg-qs-bg hover:bg-qs-card">
        Cancel
    </a>
    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-qs-text bg-qs-accent border border-qs-accent hover:opacity-95">
        {{ $submitLabel }}
    </button>
</div>
