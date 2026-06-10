<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $enableLiveSockets = $enableLiveSockets ?? true;
        $allowPollingFallback = $allowPollingFallback ?? true;
        $requireCameraMonitoring = $requireCameraMonitoring ?? true;
        $isAssignmentMode = $isAssignmentMode ?? false;
        $assignmentClipboardBlock = $assignmentClipboardBlock ?? false;
        $assignmentAllowsText = $assignmentAllowsText ?? true;
        $assignmentAllowCode = $assignmentAllowCode ?? false;
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
    <meta name="qs-assignment-mode" content="{{ ! empty($isAssignmentMode) ? '1' : '0' }}">
    <meta name="qs-assignment-clipboard-block" content="{{ ! empty($assignmentClipboardBlock) ? '1' : '0' }}">
    <meta name="qs-assignment-allows-text" content="{{ ! empty($assignmentAllowsText) ? '1' : '0' }}">
    <meta name="qs-assignment-allow-code" content="{{ ! empty($assignmentAllowCode) ? '1' : '0' }}">
    <meta name="qs-exam-clipboard-lock" content="{{ ! empty($examClipboardLock) ? '1' : '0' }}">
    <meta name="qs-exam-screenshot-mitigation" content="{{ ! empty($examScreenshotMitigation) ? '1' : '0' }}">
    <meta name="qs-exam-screen-record-mitigation" content="{{ ! empty($examScreenRecordMitigation) ? '1' : '0' }}">

    <title>{{ ($documentTitle ?? null) ? $documentTitle.' — '.config('app.name') : __('Exam').' — '.config('app.name') }}</title>

    @vite(array_filter([
        'resources/css/app.css',
        ! empty($isAssignmentMode) ? 'resources/css/student-assignment-take.css' : null,
        'resources/js/studentExamRuntime.js',
    ]))
</head>
<body class="font-sans antialiased {{ ! empty($isAssignmentMode) ? 'bg-[#f0f4f8]' : 'bg-qs-bg text-qs-text' }}">
    @include('layouts.partials.desktop-only-guard')
    @yield('content')
</body>
</html>
