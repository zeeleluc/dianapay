<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    public function messages(): array
    {
        return [
            'email.required' => translate('The :attribute field is required.', ['attribute' => translate('Email')]),
            'email.email' => translate('The :attribute must be a valid email address.', ['attribute' => translate('Email')]),
            'email.max' => translate('The :attribute may not be greater than :max characters.', ['attribute' => translate('Email'), 'max' => 255]),

            'password.required' => translate('The :attribute field is required.', ['attribute' => translate('Password')]),
            'password.min' => translate('The :attribute must be at least :min characters.', ['attribute' => translate('Password'), 'min' => 8]),
        ];
    }

}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4 text-green-400" :status="session('status')" />

    <form wire:submit="login">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="translate('Email')" class="text-gray-300" />
            <x-text-input
                wire:model="form.email"
                id="email"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-red-400" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="translate('Password')" class="text-gray-300" />
            <x-text-input
                wire:model="form.password"
                id="password"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-red-400" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input
                    wire:model="form.remember"
                    id="remember"
                    type="checkbox"
                    class="rounded border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 bg-gray-700"
                    name="remember"
                />
                <span class="ms-2 text-sm text-gray-400">{{ translate('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a
                    class="underline text-sm text-gray-400 hover:text-gray-500 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    href="{{ route('password.request') }}"
                    wire:navigate
                >
                    {{ translate('Forgot your password?') }}
                </a>
            @endif

            <x-button class="ms-3 bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 text-white">
                {{ translate('Log in') }}
            </x-button>
        </div>
    </form>
</div>
