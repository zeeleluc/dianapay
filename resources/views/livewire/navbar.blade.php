<nav class="bg-[#18181b] text-white px-6 py-4 flex justify-between items-center shadow-md {{ $large ? 'text-3xl' : 'text-xl' }}">
    <div class="font-bold">
        {{ config('app.name') }}
    </div>
    <div class="space-x-4">
        <button class="bg-emerald-500 text-white font-bold text-sm px-3 py-2 rounded hover:bg-emerald-600 transition">
            Login / Register
        </button>
    </div>
</nav>
