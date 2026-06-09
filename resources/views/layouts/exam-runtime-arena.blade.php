<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $enableLiveSockets = $enableLiveSockets ?? true;
        $allowPollingFallback = $allowPollingFallback ?? true;
        $requireCameraMonitoring = $requireCameraMonitoring ?? true;
        $examClipboardLock = $examClipboardLock ?? false;
        $examScreenshotMitigation = $examScreenshotMitigation ?? false;
        $examScreenRecordMitigation = $examScreenRecordMitigation ?? false;
        $documentTitle = $documentTitle ?? null;
    @endphp
    <meta charset="utf-8">
    @include('layouts.partials.viewport')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="exam-session-id" content="{{ $examSession->session_id }}">
    <meta name="exam-id" content="{{ $examSession->exam_id }}">
    <meta name="student-id" content="{{ auth()->id() }}">
    <meta name="qs-enable-live-sockets" content="{{ ! empty($enableLiveSockets) ? '1' : '0' }}">
    <meta name="qs-allow-polling-fallback" content="{{ ! empty($allowPollingFallback) ? '1' : '0' }}">
    <meta name="qs-require-camera-monitoring" content="{{ ! empty($requireCameraMonitoring) ? '1' : '0' }}">
    {{-- Arena is invigilated only; assignment/clipboard meta tags are kept for symmetry with the classic layout so the shared modules read the same flags. --}}
    <meta name="qs-assignment-mode" content="0">
    <meta name="qs-assignment-clipboard-block" content="0">
    <meta name="qs-assignment-allows-text" content="1">
    <meta name="qs-assignment-allow-code" content="0">
    <meta name="qs-exam-clipboard-lock" content="{{ ! empty($examClipboardLock) ? '1' : '0' }}">
    <meta name="qs-exam-screenshot-mitigation" content="{{ ! empty($examScreenshotMitigation) ? '1' : '0' }}">
    <meta name="qs-exam-screen-record-mitigation" content="{{ ! empty($examScreenRecordMitigation) ? '1' : '0' }}">
    <meta name="qs-exam-play-mode" content="arena">

    <title>{{ ($documentTitle ?? null) ? $documentTitle.' — '.config('app.name') : __('Exam').' — '.config('app.name') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/css/student-exam-arena.css',
        'resources/js/studentExamArena.js',
    ])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-100">
    @yield('content')
</body>
</html>
