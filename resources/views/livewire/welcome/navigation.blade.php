<nav class="bg-gray-900 text-white px-4 sm:px-6 py-6 sm:py-8 shadow-md text-lg sm:text-xl">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">
        <div class="font-bold text-center sm:text-left">
            <a href="{{ route('home') }}" wire:navigate>
                {{ config('app.name') }}
            </a>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-end sm:space-x-4 space-y-3 sm:space-y-0 w-full max-w-xs mx-auto sm:max-w-none sm:w-auto sm:mx-0">
            <livewire:language-switcher class="w-full sm:w-auto whitespace-nowrap" />

            @auth
                <x-button href="{{ url('/dashboard') }}" class="w-full sm:w-auto whitespace-nowrap">
                    {{ translate('Dashboard') }}
                </x-button>
            @else
                <x-button href="{{ route('login') }}" class="w-full sm:w-auto whitespace-nowrap">
                    {{ translate('Log in') }}
                </x-button>

                @if (Route::has('register'))
                    <x-button href="{{ route('register') }}" class="w-full sm:w-auto whitespace-nowrap">
                        {{ translate('Register') }}
                    </x-button>
                @endif
            @endauth
        </div>
    </div>
</nav>
