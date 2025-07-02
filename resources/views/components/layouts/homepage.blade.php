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
<body class="antialiased bg-dark">

@include('livewire.welcome.navigation')

<main>
    {{ $slot }}
</main>

<x-footer />

@livewireScripts
@vite('resources/js/app.js')

</body>
</html>
