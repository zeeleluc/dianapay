@props([
    'href' => null,
    'type' => 'submit',
    'variant' => 'primary', // new prop to toggle variants
])

@php
    $baseClasses = 'inline-flex items-center px-4 py-2 border border-transparent rounded-md text-sm font-bold uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150';

    $colorClasses = match ($variant) {
        'secondary' => 'bg-blue-600 text-white hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:ring-blue-500',
        default => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:bg-emerald-700 active:bg-emerald-900 focus:ring-emerald-500',
    };

    $classes = $baseClasses . ' ' . $colorClasses;
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
