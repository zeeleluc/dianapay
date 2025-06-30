<nav class="bg-gray-900 text-white px-6 py-8 flex justify-between items-center shadow-md text-xl">
    <div class="font-bold">
        {{ config('app.name') }}
    </div>
    <div class="space-x-4">
        <x-primary-button href="{{ route('login') }}">{{ __('Login') }}</x-primary-button>
        <x-primary-button href="{{ route('register') }}">{{ __('Register') }}</x-primary-button>
    </div>
</nav>
