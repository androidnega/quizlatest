<x-layouts.admin>
    <x-slot name="title">System settings</x-slot>
    <x-slot name="subtitle">Integrations and defaults (secrets are encrypted; API keys are never shown in full)</x-slot>

    <form method="post" action="{{ route('admin.settings.update') }}" class="space-y-10">
        @csrf
        @method('PUT')

        <div class="qs-surface border border-camel rounded-lg p-6 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold qs-heading">SMS (Arkesel)</h3>
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">API key</label>
                <input type="password" name="arkesel_api_key" autocomplete="off"
                    class="w-full max-w-xl rounded border border-gray-300 px-3 py-2 text-sm"
                    placeholder="{{ $arkesel_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @if($arkesel_key_locked) disabled @endif />
                @if($arkesel_key_locked)
                    <p class="text-xs text-amber-700 mt-1">Locked. Unlock below to change.</p>
                @endif
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Sender ID</label>
                <input type="text" name="arkesel_sender_id" value="{{ old('arkesel_sender_id', $arkesel_sender_id) }}"
                    class="w-full max-w-xl rounded border border-gray-300 px-3 py-2 text-sm"
                    @if($arkesel_sender_locked) disabled @endif />
            </div>
        </div>

        <div class="qs-surface border border-camel rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-semibold qs-heading">AI API</h3>
            <div>
                <label class="block text-sm text-gray-700 mb-1">API key</label>
                <input type="password" name="ai_api_key" autocomplete="off"
                    class="w-full max-w-xl rounded border border-gray-300 px-3 py-2 text-sm"
                    placeholder="{{ $ai_api_key_masked ? '•••••••• (enter new to replace)' : 'Not set' }}"
                    @if($ai_key_locked) disabled @endif />
            </div>
            <div>
                <label class="block text-sm text-gray-700 mb-1">Model name</label>
                <input type="text" name="ai_model_name" value="{{ old('ai_model_name', $ai_model_name) }}"
                    class="w-full max-w-xl rounded border border-gray-300 px-3 py-2 text-sm"
                    @if($ai_model_locked) disabled @endif />
            </div>
        </div>

        <div class="qs-surface border border-camel rounded-lg p-6 space-y-4">
            <h3 class="text-lg font-semibold qs-heading">Default proctoring (JSON)</h3>
            <textarea name="default_proctoring_settings" rows="8" class="w-full font-mono text-sm rounded border border-gray-300 px-3 py-2"
                @if($proctoring_locked) disabled @endif>{{ old('default_proctoring_settings', $proctoring_json) }}</textarea>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-white bg-camel border border-camel rounded-md hover:bg-camel/90">
                Save changes
            </button>
        </div>
    </form>

    <div class="mt-10 qs-surface border border-beige rounded-lg p-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Lock / unlock (admin only)</h3>
        <p class="text-xs text-gray-600 mb-4">When locked, only a super admin can change the value. In this app, the <code class="text-xs">admin</code> role is treated as super admin.</p>
        <ul class="text-sm space-y-2">
            @foreach (['arkesel_api_key' => 'Arkesel API key', 'arkesel_sender_id' => 'Arkesel sender ID', 'ai_api_key' => 'AI API key', 'ai_model_name' => 'AI model name', 'default_proctoring_settings' => 'Default proctoring JSON'] as $k => $label)
                <li class="flex flex-wrap items-center gap-2">
                    <span class="text-gray-700">{{ $label }}</span>
                    <form method="post" action="{{ route('admin.settings.lock') }}" class="inline">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" class="text-xs px-2 py-1 rounded bg-sage text-white">Lock</button>
                    </form>
                    <form method="post" action="{{ route('admin.settings.unlock') }}" class="inline">@csrf
                        <input type="hidden" name="key" value="{{ $k }}" />
                        <button type="submit" class="text-xs px-2 py-1 rounded border border-gray-300">Unlock</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
</x-layouts.admin>
