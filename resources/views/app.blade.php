<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link href="{{ asset('fonts/satoshi/css/satoshi.css') }}" rel="stylesheet" />

    <!-- Favicon for browsers -->
    <link rel="icon" type="image/png" sizes="32x32" href="/favico.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favico.png">

    <!-- Favicon for iOS devices -->
    <link rel="apple-touch-icon" sizes="180x180" href="/favico.png">

    <!-- Favicon for Android devices -->
    <link rel="icon" type="image/png" sizes="192x192" href="/favico.png">

    <!-- Scripts -->
    @routes
    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
