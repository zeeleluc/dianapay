<div class="relative bg-darker text-white overflow-hidden">
    <!-- Top Wave -->
    <div class="absolute top-0 left-0 w-full leading-[0] rotate-180">
        <svg class="relative block w-full h-[160px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path fill="#1C1E23" d="M0,256 C180,192 360,320 540,256 C720,192 900,320 1080,256 C1260,192 1440,320 1440,320 L1440,320 L0,320 Z"></path>
        </svg>
    </div>

    <!-- Section Content -->
    <div class="max-w-4xl mx-auto flex flex-col justify-center items-center text-center pt-24 pb-24 px-4 sm:px-6 relative z-10">
        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight">
            {!! $slot !!}
        </h1>
    </div>

    <!-- Bottom Wave -->
    <div class="absolute bottom-0 left-0 w-full leading-[0]">
        <svg class="relative block w-full h-[160px]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" preserveAspectRatio="none">
            <path fill="#1C1E23" d="M0,256 C180,192 360,320 540,256 C720,192 900,320 1080,256 C1260,192 1440,320 1440,320 L1440,320 L0,320 Z"></path>
        </svg>
    </div>
</div>
