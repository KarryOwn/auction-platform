<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|playfair-display:600,700" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="theme-shell min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
            <div class="w-full">
                @include('partials.maintenance-banner')
            </div>
            <div>
                <a href="/" class="inline-flex scale-125 hover:scale-110 transition-transform duration-200 my-8">
                    <x-application-logo />
                </a>
            </div>

            <div class="theme-card w-full sm:max-w-md mt-6 px-8 py-8 overflow-hidden">
                {{ $slot }}
            </div>
        </div>

        <x-support-chat-widget />

        @stack('scripts')
    </body>
</html>
