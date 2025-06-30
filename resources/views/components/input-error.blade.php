@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge([
            'class' => 'text-sm text-red-400 space-y-1 bg-gray-900 border border-red-600 rounded-md p-3 shadow-sm'
        ]) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
