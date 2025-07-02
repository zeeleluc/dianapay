<div class="relative language-switcher text-white text-sm w-full max-w-xs sm:max-w-none">
    <button
        type="button"
        class="dropdown-toggle flex items-center space-x-2 bg-darker hover:bg-gray-700 px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full sm:w-auto"
        wire:click="$toggle('open')"
    >
        <span>{{ $flags[$locale] ?? '🏳️' }}</span>
        <span class="uppercase font-semibold">{{ $localeLabels[$locale] }}</span>
        <svg class="w-4 h-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"
             xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    @if ($open ?? false)
        <ul
            class="dropdown-menu absolute right-0 mt-1 w-full sm:w-64 bg-darker border border-gray-700 rounded shadow-lg z-50 grid grid-cols-3 gap-2 p-2 text-sm"
            style="min-width: 12rem;"
        >
            @foreach ($allowedLocales as $lang)
                <li>
                    <button
                        type="button"
                        wire:click="switchLocale('{{ $lang }}')"
                        class="w-full flex items-center space-x-2 hover:bg-indigo-600 rounded px-2 py-1"
                    >
                        <span>{{ $flags[$lang] ?? '🏳️' }}</span>
                        <span class="uppercase font-semibold">{{ $localeLabels[$lang] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
