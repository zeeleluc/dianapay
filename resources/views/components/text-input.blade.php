@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' => 'block w-full rounded px-3 py-2 bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 ' . ($disabled ? 'opacity-50 cursor-not-allowed' : '')
    ]) }}
>
