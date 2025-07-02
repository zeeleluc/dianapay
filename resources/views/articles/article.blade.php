<x-layouts.homepage>
    <div class="container mx-auto px-4 py-10 max-w-7xl">

        <nav class="mb-10 bg-darkest text-gray-200 rounded-lg px-8 py-6 text-sm shadow-md border border-dark flex flex-wrap gap-2">
            @foreach ($breadcrumbs as $crumb)
                @if ($loop->last || !$crumb['url'])
                    <span class="font-semibold text-white cursor-default">{{ $crumb['label'] }}</span>
                @else
                    <a href="{{ $crumb['url'] }}" class="hover:underline text-soft-yellow font-semibold">
                        {{ $crumb['label'] }}
                    </a>
                    <span class="mx-1 text-gray-400">/</span>
                @endif
            @endforeach
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            {{-- Left Column (Sidebar) --}}
            <aside class="md:col-span-1 bg-darkest text-gray-200 p-8 rounded-2xl border border-dark shadow">
                {!! $sidebar ?? '' !!}
            </aside>

            {{-- Right/Main Content Column --}}
            <main class="md:col-span-3 bg-darkest text-gray-100 p-8 rounded-2xl border border-dark shadow prose prose-invert max-w-none">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold mb-4 drop-shadow-lg leading-tight">
                    {{ translate($title) }}
                </h1>
                {!! $content ?? '' !!}
            </main>
        </div>

    </div>
</x-layouts.homepage>
