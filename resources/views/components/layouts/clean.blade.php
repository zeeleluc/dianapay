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


<main class="min-h-screen flex flex-col items-center justify-center pt-6 space-y-6 w-80 md:w-96 mx-auto">

    <a class="text-white" href="/" wire:navigate>
        <x-application-logo class="w-20 h-20 text-white" />
    </a>

    <div class="w-full bg-darker rounded-md px-4 py-2 flex justify-center">
        <livewire:language-switcher class="whitespace-nowrap" />
    </div>

    {{ $slot }}
</main>

<x-wavy-section>
    {!! translate('Crypto Payments Made Effortless') !!}
</x-wavy-section>

<x-footer />

@livewireScripts
@vite('resources/js/app.js')
</body>
</html>
