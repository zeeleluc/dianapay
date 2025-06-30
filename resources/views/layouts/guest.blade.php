<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-800">

        @include('livewire.welcome.navigation')
        <div class="min-h-[calc(100vh-6rem)] flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-800">
            <div>
                <a class="text-white" href="/" wire:navigate>
                    <x-application-logo class="w-20 h-20 text-white" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 text-white bg-gray-950 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
