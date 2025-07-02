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

// Regular dynamic article route
    Route::get('/articles/{slug1}/{slug2?}/{slug3?}', [\App\Http\Controllers\ArticleController::class, 'show'])
        ->name('articles.show');

// Edge-case routes without "articles" prefix
    Route::get('/about', fn () => app(\App\Http\Controllers\ArticleController::class)->show('about'))
        ->name('about');

    Route::get('/faq', fn () => app(\App\Http\Controllers\ArticleController::class)->show('faq'))
        ->name('faq');

    Route::get('/pricing', fn () => app(\App\Http\Controllers\ArticleController::class)->show('pricing'))
        ->name('pricing');

    Route::get('/', Home::class)->name('home');
    Route::get('payment/anonymous', AnonymousPaymentRequestForm::class)->name('payment.anonymous.create');
    Route::get('payment/anonymous/request/{uuid}', [PublicAnonymousPaymentController::class, 'request'])->name('payment.anonymous.request');
    Route::get('payment/anonymous/{uuid}', AnonymousPayment::class)->name('payment.anonymous.show');

    Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

    require __DIR__.'/auth.php';
});
