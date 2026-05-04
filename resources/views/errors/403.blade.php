@php
    $detail = trim((string) ($exception->getMessage() ?? ''));
    $message = $detail !== '' ? $detail : __('You do not have permission to access this page.');
@endphp
@include('errors.partials.shell', [
    'code' => '403',
    'heading' => __('Access denied'),
    'message' => $message,
])
