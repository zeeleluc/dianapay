{{-- application-logo.blade.php --}}
<span>
    <img alt="Crypt Me Up Logo" {{ $attributes->merge(['class' => 'text-white text-xl font-bold']) }} src="{{ @asset('images/logo.png') }}" />
</span>
