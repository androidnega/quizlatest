@include('errors.partials.shell', [
    'code' => '503',
    'heading' => __('Service unavailable'),
    'message' => __('The service is temporarily unavailable. Please try again shortly.'),
])
