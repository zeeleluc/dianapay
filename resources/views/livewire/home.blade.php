<div>
    <div class="min-h-[calc(100vh-6rem)] flex flex-col justify-center items-center text-center px-4 sm:px-6
      bg-gradient-to-b from-black via-gray-900 to-gray-800 text-white"
    >
        <h1 class="text-4xl sm:text-5xl md:text-6xl font-extrabold mb-4 drop-shadow-lg leading-tight">
            {{ config('app.name') }}
        </h1>

        <p class="text-base sm:text-lg md:text-xl mb-8 text-gray-300 max-w-xl">
            {{ translate('Crypto Payments — Easy, Fast, Global.') }}
        </p>

        <x-button variant="secondary" class="text-base sm:text-lg md:text-xl py-3 px-5 sm:py-4 sm:px-6">
            {{ translate('Create Anonymous Single Payment') }}
        </x-button>
    </div>

    {{-- New Section: Exchange Rates --}}
    <section class="bg-gray-900 text-gray-100 py-16 px-4 sm:px-6 md:px-12 border-t border-gray-700 shadow-inner">
        <div class="container mx-auto space-y-12">
            <h2 class="text-3xl sm:text-4xl font-bold text-center mb-10">{{ translate('Live Exchange Rates') }}</h2>

            <livewire:currency-rates-table />
        </div>
    </section>
</div>
