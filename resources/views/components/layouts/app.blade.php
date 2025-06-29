<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ config('app.name') }}</title>

    @livewireStyles
    @vite('resources/css/app.css')
</head>
<body class="antialiased bg-[#1C1E22]">

@livewire('navbar')

<main>
    {{ $slot }}
</main>

@livewireScripts
@vite('resources/js/app.js')
</body>
</html>
