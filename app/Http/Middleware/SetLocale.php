<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $allowedLocales = config('locales.allowed', ['en']);
        $locale = $request->route('locale');

        // Bypass Livewire, pincode, sniper
        if ($request->is('livewire/*') || $request->is('pincode') || $request->is('sniper')) {
            return $next($request);
        }

        // Invalid locale in route -> redirect
        if ($locale && !in_array($locale, $allowedLocales)) {
            $locale = Session::get('locale', config('app.locale'));
        }

        // No locale -> redirect to default
        if (!$locale) {
            $locale = Session::get('locale', config('app.locale'));
            if (!in_array($locale, $allowedLocales)) {
                $browserLocale = substr($request->getPreferredLanguage($allowedLocales), 0, 5);
                $locale = in_array($browserLocale, $allowedLocales) ? $browserLocale : config('app.locale');
            }

            $path = $request->path() === '/' ? '' : $request->path();
            return Redirect::to("/{$locale}/{$path}", 301);
        }

        // Set locale
        Session::put('locale', $locale);
        App::setLocale($locale);

        return $next($request);
    }
}
