<nav class="bg-dark text-white px-4 sm:px-6 py-12 sm:py-14 text-lg sm:text-xl">
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center space-y-4 sm:space-y-0">

            <div class="flex items-center space-x-4">
                <a class="text-white" href="/" wire:navigate>
                    <x-application-logo class="w-14 h-14 text-white" />
                </a>
                <div class="text-center sm:text-left">
                    <a href="{{ route('home', get_locale()) }}" wire:navigate class="font-bold text-3xl block text-white leading-tight">
                        {{ config('app.name') }}
                    </a>
                    <p class="text-base text-gray-400 mt-1">
                        Fast & Easy Crypto Payments
                    </p>
                </div>
            </div>


            <div class="flex flex-col sm:flex-row items-center justify-end sm:space-x-4 space-y-3 sm:space-y-0 w-full max-w-xs mx-auto sm:max-w-none sm:w-auto sm:mx-0">
                <livewire:language-switcher class="w-full sm:w-auto whitespace-nowrap" />

{{--                @auth--}}
{{--                    <x-button href="{{ url('/dashboard') }}" class="w-full sm:w-auto whitespace-nowrap">--}}
{{--                    {{ translate('Dashboard') }}--}}
{{--                    </x-button>--}}
{{--                @else--}}
{{--                    <x-button href="{{ route('login', get_locale()) }}" class="w-full sm:w-auto whitespace-nowrap">--}}
{{--                    {{ translate('Log in') }}--}}
{{--                    </x-button>--}}

{{--                    @if (Route::has('register'))--}}
{{--                        <x-button href="{{ route('register', get_locale()) }}" class="w-full sm:w-auto whitespace-nowrap">--}}
{{--                        {{ translate('Register') }}--}}
{{--                        </x-button>--}}
{{--                    @endif--}}
{{--                @endauth--}}
            </div>
        </div>
    </div>
</nav>
