<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Livewire\Home;
use App\Livewire\AnonymousPayment;
use App\Livewire\Forms\AnonymousPaymentRequestForm;
use App\Http\Controllers\PublicAnonymousPaymentController;


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
    Route::get('payment/anonymous', AnonymousPaymentRequestForm::class)->name('payment.anonymous.create');
    Route::get('payment/anonymous/request/{uuid}', [PublicAnonymousPaymentController::class, 'request'])->name('payment.anonymous.request');
    Route::get('payment/anonymous/{uuid}', AnonymousPayment::class)->name('payment.anonymous.show');

    Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

    require __DIR__.'/auth.php';
});
