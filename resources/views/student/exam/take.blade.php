@extends('layouts.exam-runtime')

@section('content')
<div id="exam-app" class="min-h-screen flex flex-col">
    <header class="border-b border-[#CFAC81] bg-white shadow-sm shrink-0">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 id="exam-title" class="text-lg font-semibold qs-heading">{{ __('Loading…') }}</h1>
                <p id="exam-subtitle" class="text-sm text-slate-500 hidden"></p>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" id="btn-fullscreen"
                    class="text-sm px-3 py-1.5 rounded border border-[#CFAC81] hover:bg-amber-50">
                    {{ __('Fullscreen') }}
                </button>
                <div id="exam-timer" class="font-mono text-xl font-semibold tabular-nums text-[#6E8B43]"
                    aria-live="polite">--:--</div>
            </div>
        </div>
    </header>

    <div class="flex flex-1 flex-col md:flex-row max-w-6xl mx-auto w-full min-h-0">
        <aside class="w-full md:w-52 shrink-0 border-b md:border-b-0 md:border-r border-[#CFAC81] bg-white p-3 overflow-y-auto max-h-48 md:max-h-none">
            <p class="text-xs font-semibold text-slate-500 uppercase mb-2">{{ __('Questions') }}</p>
            <nav id="question-nav" class="flex flex-wrap md:flex-col gap-2"></nav>
        </aside>

        <main class="flex-1 flex flex-col min-h-0 overflow-hidden">
            <div id="exam-banner" class="hidden px-4 py-2 text-sm border-b bg-amber-50 border-amber-200 text-amber-900"></div>
            <div id="exam-main" class="flex-1 overflow-y-auto p-4 md:p-6">
                <p id="exam-loading" class="text-slate-600">{{ __('Loading exam…') }}</p>
                <div id="question-container" class="hidden space-y-4"></div>
            </div>
        </main>
    </div>

    <footer class="border-t border-[#CFAC81] bg-white shrink-0">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div id="save-indicator" class="text-sm text-slate-500">{{ __('Answers save automatically.') }}</div>
            <div class="flex items-center gap-2">
                <span id="video-status" class="text-xs text-slate-400 hidden md:inline">{{ __('Camera required for proctoring.') }}</span>
                <button type="button" id="btn-submit"
                    class="px-5 py-2 rounded bg-[#6E8B43] text-white font-medium hover:opacity-95 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Submit exam') }}
                </button>
            </div>
        </div>
    </footer>

    <video id="proctoring-video" class="fixed w-px h-px opacity-0 pointer-events-none" playsinline muted autoplay></video>
</div>
@endsection
