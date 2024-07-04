<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if (App::environment('production'))
            <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        @endif

        <link rel="apple-touch-icon" href="public/assets/logo.png" />
        <title inertia>{{ config('app.name', 'Payment') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        {{-- <link rel="stylesheet" href="https://ttpayment-gateway.currenttech.pro/build/assets/app-Bw6M7ecn.css"> --}}

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
