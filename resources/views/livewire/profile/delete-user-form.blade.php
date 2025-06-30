<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-white">
            {{ translate('Delete Account') }}
        </h2>

        <p class="mt-1 text-sm text-gray-300">
            {{ translate('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        class="bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white"
    >
        {{ translate('Delete Account') }}
    </x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6 bg-gray-800 rounded-md text-white">

            <h2 class="text-lg font-medium">
                {{ translate('Are you sure you want to delete your account?') }}
            </h2>

            <p class="mt-1 text-sm text-gray-400">
                {{ translate('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ translate('Password') }}" class="sr-only text-gray-300" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 bg-gray-700 border-gray-600 text-white"
                    placeholder="{{ translate('Password') }}"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400" />
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <x-secondary-button
                    x-on:click="$dispatch('close')"
                    class="bg-gray-600 hover:bg-gray-700 text-white focus:ring-gray-500"
                >
                    {{ translate('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="bg-red-600 hover:bg-red-700 focus:ring-red-500 text-white">
                    {{ translate('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
