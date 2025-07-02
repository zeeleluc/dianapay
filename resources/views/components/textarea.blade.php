@props([
    'id' => null,
    'name' => null,
    'rows' => 3,
    'class' => '',
])

<textarea
    {{ $attributes->merge([
        'id' => $id,
        'name' => $name,
        'rows' => $rows,
        'class' => "block w-full rounded px-3 py-2 bg-dark text-gray-100 border-none focus:outline-none focus:ring-2 focus:ring-blue-500 $class"
    ]) }}
>{{ $slot ?? old($name) }}</textarea>
