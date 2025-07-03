<div>
    @if ($visible)
        <div
            class="bg-yellow-100 text-yellow-900 py-2 text-sm font-medium text-center w-full"
            role="alert"
        >
            {{ translate('You are viewing an auto-translated version of the original English content') }}
            <button
                wire:click="dismiss"
                class="text-yellow-900 font-bold text-lg mx-1"
                aria-label="Close notice"
                type="button"
            >
                &times;
            </button>
        </div>
    @endif
</div>
