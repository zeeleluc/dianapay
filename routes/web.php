<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use App\Livewire\Home;
use App\Livewire\AnonymousPayment;
use App\Livewire\Forms\AnonymousPaymentRequestForm;
use App\Http\Controllers\PublicAnonymousPaymentController;
use App\Http\Controllers\SniperController;

Route::middleware('web')->group(function () {

    // Show pincode form
    Route::get('/pincode', function () {
        return view('pincode');
    })->name('pincode.form');

    // Handle form submission
    Route::post('/pincode', function (\Illuminate\Http\Request $request) {
        if ($request->input('pincode') == config('pincode.code')) {
            Session::put('pincode_validated', true);
            return redirect()->intended('/sniper');
        }

        return back()->withErrors(['pincode' => 'Invalid pincode']);
    })->name('pincode.check');

    // Example protected route
//    Route::get('/sniper', function () {
//        return "This is protected by a pincode!";
//    })->middleware('pincode')->name('sniper');
    Route::get('sniper', [SniperController::class, 'index'])
        ->name('sniper')
        ->middleware('pincode');

    // Language switcher
    Route::get('/lang/{locale}', function ($locale) {
        $allowedLocales = config('locales.allowed', ['en']);
        if (!in_array($locale, $allowedLocales)) {
            abort(404);
        }

        Session::put('locale', $locale);
        \Illuminate\Support\Facades\App::setLocale($locale);

        $referer = request()->headers->get('referer');
        if ($referer) {
            $path = parse_url($referer, PHP_URL_PATH);
            // Replace existing locale prefix with new locale
            $newPath = preg_replace('/^\/[a-z]{2}(-[A-Z]{2})?\//', "/{$locale}/", $path) ?: "/{$locale}{$path}";
            return Redirect::to($newPath, 301);
        }

        return Redirect::to("/{$locale}", 301);
    })->name('lang.switch');

    // Main routes with locale prefix and middleware
    Route::prefix('{locale}')
        ->middleware('setLocale')
        ->group(function () {

            Route::get('/', Home::class)->name('home');
            Route::get('payment/anonymous', AnonymousPaymentRequestForm::class)->name('payment.anonymous.create');
            Route::get('payment/anonymous/request/{uuid}', [PublicAnonymousPaymentController::class, 'request'])->name('payment.anonymous.request');
            Route::get('payment/anonymous/{uuid}', AnonymousPayment::class)->name('payment.anonymous.show');

            Route::get('/articles/{slug1}/{slug2?}/{slug3?}', [\App\Http\Controllers\ArticleController::class, 'show'])->name('articles.show');

            Route::get('/terms', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'terms'))->name('terms');
            Route::get('/privacy', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'privacy'))->name('privacy');
            Route::get('/about', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'about'))->name('about');
            Route::get('/contact', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'contact'))->name('contact');
            Route::get('/faq', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'faq'))->name('faq');
            Route::get('/pricing', fn () => app(\App\Http\Controllers\ArticleController::class)->show(request()->route('locale'), 'pricing'))->name('pricing');

            Route::view('dashboard', 'dashboard')->middleware(['auth', 'verified'])->name('dashboard');
            Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

            require __DIR__.'/auth.php';
        });

    // Redirect requests without locale prefix to default locale,
    // but allow specific routes to bypass
    $excluded = ['pincode', 'sniper'];

    Route::get('/{path}', function ($path) use ($excluded) {
        $locale = Session::get('locale', config('app.locale'));

        if (in_array($path, $excluded) || str_starts_with($path, 'livewire/')) {
            abort(404); // or just return $next if you want
        }

        return Redirect::to("/{$locale}/{$path}", 301);
    })->where('path', '.*')->name('locale.redirect');
});
