@extends('layouts.exam-runtime')

@section('content')
<div id="exam-app" class="min-h-screen flex flex-col">
    <header class="border-b border-qs-soft bg-qs-bg shadow-sm shrink-0">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 id="exam-title" class="text-lg font-semibold qs-heading">{{ __('Loading…') }}</h1>
                <p id="exam-subtitle" class="text-sm text-qs-muted hidden"></p>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" id="btn-fullscreen"
                    class="text-sm px-3 py-1.5 rounded border border-qs-soft bg-qs-bg hover:bg-qs-card">
                    {{ __('Fullscreen') }}
                </button>
                <div id="exam-timer" class="font-mono text-xl font-semibold tabular-nums text-qs-accent"
                    aria-live="polite">--:--</div>
                <span id="fullscreen-exit-notice" class="hidden max-w-[220px] shrink-0 text-xs text-qs-text"
                    role="status"></span>
            </div>
        </div>
    </header>

    <div class="flex flex-1 flex-col md:flex-row max-w-6xl mx-auto w-full min-h-0">
        <aside class="w-full md:w-52 shrink-0 border-b md:border-b-0 md:border-r border-qs-soft bg-qs-bg p-3 overflow-y-auto max-h-48 md:max-h-none">
            <p class="text-xs font-semibold text-qs-muted uppercase mb-2">{{ __('Questions') }}</p>
            <nav id="question-nav" class="flex flex-wrap md:flex-col gap-2"></nav>
        </aside>

        <main class="flex-1 flex flex-col min-h-0 overflow-hidden">
            <div id="exam-banner" class="hidden px-4 py-2 text-sm border-b border-qs-accent/35 bg-qs-accent/15 text-qs-text"></div>
            <div id="exam-main" class="flex-1 overflow-y-auto p-4 md:p-6">
                <p id="exam-loading" class="text-qs-muted">{{ __('Loading exam…') }}</p>
                <div id="question-container" class="hidden space-y-4"></div>
            </div>
        </main>
    </div>

    <footer class="border-t border-qs-soft bg-qs-bg shrink-0">
        <div class="max-w-6xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
            <div id="save-indicator" class="text-sm text-qs-muted">{{ __('Answers save automatically.') }}</div>
            <div class="flex items-center gap-2">
                <span id="video-status" class="text-xs text-qs-muted hidden md:inline">{{ __('Camera required for proctoring.') }}</span>
                <button type="button" id="btn-submit"
                    class="qs-btn-primary px-5 py-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    {{ __('Submit exam') }}
                </button>
            </div>
        </div>
    </footer>

    <video id="proctoring-video" class="fixed w-px h-px opacity-0 pointer-events-none" playsinline muted autoplay></video>

    <div id="essay-clipboard-toast" role="status" aria-live="polite"
        class="pointer-events-none fixed bottom-20 left-1/2 z-50 hidden max-w-sm -translate-x-1/2 rounded-lg border border-qs-soft bg-qs-card px-4 py-2 text-center text-sm text-qs-text shadow-lg">
    </div>
</div>
@endsection
