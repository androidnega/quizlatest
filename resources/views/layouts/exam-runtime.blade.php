<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="exam-session-id" content="{{ $examSession->session_id }}">
    <meta name="exam-id" content="{{ $examSession->exam_id }}">
    <meta name="student-id" content="{{ auth()->id() }}">

    <title>{{ __('Exam') }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/studentExamRuntime.js'])
</head>
<body class="font-sans antialiased bg-qs-bg text-qs-text">
    @yield('content')
</body>
</html>
