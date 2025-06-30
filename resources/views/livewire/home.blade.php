<div class="min-h-[calc(100vh-6rem)] flex flex-col justify-center items-center text-center px-6
  bg-gradient-to-b from-black via-gray-900 to-gray-800
  text-white"
>
    <h1 class="text-6xl font-extrabold mb-4 drop-shadow-lg">
        {{ config('app.name') }}
    </h1>

    <p class="text-xl mb-8 text-gray-300 max-w-xl">
        {{ __('Crypto Payments — Easy, Fast, Global.') }}
    </p>

    <x-button variant="secondary" class="text-xl py-4 px-5">
        Create Anonymous Single Payment
    </x-button>
</div>
