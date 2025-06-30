<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ], [
                'current_password.required' => translate('The current password field is required.'),
                'current_password.string' => translate('The current password must be a string.'),
                'current_password.current_password' => translate('The current password is incorrect.'),

                'password.required' => translate('The new password field is required.'),
                'password.string' => translate('The new password must be a string.'),
                'password.confirmed' => translate('The new password confirmation does not match.'),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-white">
            {{ translate('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-300">
            {{ translate('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 space-y-6">
        <div>
            <x-input-label for="update_password_current_password" :value="translate('Current Password')" class="text-gray-300" />
            <x-text-input wire:model="current_password" id="update_password_current_password" name="current_password" type="password" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white" autocomplete="current-password" />
            <x-input-error :messages="$errors->get('current_password')" class="mt-2 text-red-400" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="translate('New Password')" class="text-gray-300" />
            <x-text-input wire:model="password" id="update_password_password" name="password" type="password" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="translate('Confirm Password')" class="text-gray-300" />
            <x-text-input wire:model="password_confirmation" id="update_password_password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full bg-gray-700 border-gray-600 text-white" autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400" />
        </div>

        <div class="flex items-center gap-4">
            <x-button class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500 text-white">
                {{ translate('Submit') }}
            </x-button>

            <x-action-message class="text-emerald-400" on="password-updated">
                {{ translate('Submitted.') }}
            </x-action-message>
        </div>
    </form>
</section>
