<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Return translated validation messages.
     */
    protected function messages(): array
    {
        return [
            'name.required' => translate('The :attribute field is required.', ['attribute' => translate('Name')]),
            'name.string' => translate('The :attribute must be a string.', ['attribute' => translate('Name')]),
            'name.max' => translate('The :attribute may not be greater than :max characters.', ['attribute' => translate('Name'), 'max' => 255]),

            'email.required' => translate('The :attribute field is required.', ['attribute' => translate('Email')]),
            'email.string' => translate('The :attribute must be a string.', ['attribute' => translate('Email')]),
            'email.lowercase' => translate('The :attribute must be lowercase.', ['attribute' => translate('Email')]),
            'email.email' => translate('The :attribute must be a valid email address.', ['attribute' => translate('Email')]),
            'email.max' => translate('The :attribute may not be greater than :max characters.', ['attribute' => translate('Email'), 'max' => 255]),
            'email.unique' => translate('The :attribute has already been taken.', ['attribute' => translate('Email')]),

            'password.required' => translate('The :attribute field is required.', ['attribute' => translate('Password')]),
            'password.string' => translate('The :attribute must be a string.', ['attribute' => translate('Password')]),
            'password.confirmed' => translate('The :attribute confirmation does not match.', ['attribute' => translate('Password')]),
            'password.min' => translate('The :attribute must be at least :min characters.', ['attribute' => translate('Password'), 'min' => 8]),

            // No validation for password_confirmation since it's checked by 'confirmed' rule
        ];
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], $this->messages());

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
};
?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="translate('Name')" class="text-gray-300" />
            <x-text-input
                wire:model="name"
                id="name"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
            />
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-400" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="translate('Email')" class="text-gray-300" />
            <x-text-input
                wire:model="email"
                id="email"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="email"
                name="email"
                required
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="translate('Password')" class="text-gray-300" />
            <x-text-input
                wire:model="password"
                id="password"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="translate('Confirm Password')" class="text-gray-300" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                class="block mt-1 w-full bg-gray-700 border-gray-600 text-white"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a
                class="underline text-sm text-gray-400 hover:text-gray-500 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                href="{{ route('login') }}"
                wire:navigate
            >
                {{ translate('Already registered?') }}
            </a>

            <x-button class="ms-4 bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 text-white">
                {{ translate('Register') }}
            </x-button>
        </div>
    </form>
</div>
