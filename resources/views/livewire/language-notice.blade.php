<div>
    @if ($visible)
        <div
            class="bg-yellow-100 text-yellow-900 text-sm font-medium text-center h-[35px] leading-[35px] w-full flex items-center justify-center gap-2"
            role="alert"
        >
            <strong>{{ translate('Notice') }}</strong> {{ translate('You are viewing an auto-translated version of the original English content') }}
            <button
                wire:click="dismiss"
                class="text-yellow-900 font-bold text-lg leading-none"
                aria-label="Close notice"
                type="button"
            >&times;</button>
        </div>
    @endif
</div>
