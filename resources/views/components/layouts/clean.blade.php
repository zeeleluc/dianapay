<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>{{ config('app.name') }}</title>

    @livewireStyles
    @vite('resources/css/app.css')
</head>
<body class="antialiased bg-gray-950">

<main class="min-h-screen flex flex-col items-center justify-center pt-6 space-y-6 w-80 md:w-96 mx-auto">
    <div class="w-full bg-gray-800 rounded-md px-4 py-2 flex justify-center">
        <livewire:language-switcher class="whitespace-nowrap" />
    </div>

    {{ $slot }}
</main>

@livewireScripts
@vite('resources/js/app.js')
</body>
</html>
