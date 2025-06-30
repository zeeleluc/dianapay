<nav class="bg-gray-900 text-white px-6 py-8 flex justify-between items-center shadow-md text-xl">
    <div class="font-bold">
        {{ config('app.name') }}
    </div>

    <div class="space-x-4">
        @auth
            <x-button href="{{ url('/dashboard') }}">
                {{ __('Dashboard') }}
            </x-button>
        @else
            <x-button href="{{ route('login') }}">
                {{ __('Log in') }}
            </x-button>

            @if (Route::has('register'))
                <x-button href="{{ route('register') }}">
                    {{ __('Register') }}
                </x-button>
            @endif
        @endauth
    </div>
</nav>
