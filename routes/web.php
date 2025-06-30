<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Livewire\Home;

Route::middleware('web')->group(function () {

    // Route to switch language
    Route::get('/lang/{locale}', function ($locale) {
        $allowedLocales = config('locales.allowed', ['en']);

        if (!in_array($locale, $allowedLocales)) {
            abort(404);
        }

        Session::put('locale', $locale);

        return Redirect::back();
    });

    Route::get('/', Home::class)->name('home');

    Route::view('dashboard', 'dashboard')
        ->middleware(['auth', 'verified'])
        ->name('dashboard');

    Route::view('profile', 'profile')
        ->middleware(['auth'])
        ->name('profile');

    require __DIR__.'/auth.php';
});
