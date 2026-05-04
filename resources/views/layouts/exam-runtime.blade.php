<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $enableLiveSockets = $enableLiveSockets ?? true;
        $allowPollingFallback = $allowPollingFallback ?? true;
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="exam-session-id" content="{{ $examSession->session_id }}">
    <meta name="exam-id" content="{{ $examSession->exam_id }}">
    <meta name="student-id" content="{{ auth()->id() }}">
    <meta name="qs-enable-live-sockets" content="{{ ! empty($enableLiveSockets) ? '1' : '0' }}">
    <meta name="qs-allow-polling-fallback" content="{{ ! empty($allowPollingFallback) ? '1' : '0' }}">

    <title>{{ __('Exam') }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/studentExamRuntime.js'])
</head>
<body class="font-sans antialiased bg-qs-bg text-qs-text">
    @yield('content')
</body>
</html>
