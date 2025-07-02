<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $allowedLocales = config('locales.allowed', ['en']);

        // Priority 1: get lang from route parameter {lang}
        $locale = $request->route('lang');

        if (!$locale || !in_array($locale, $allowedLocales)) {
            // Priority 2: try session
            $locale = Session::get('locale');

            if (!$locale || !in_array($locale, $allowedLocales)) {
                // Priority 3: try browser preferred language
                $browserLocale = substr($request->getPreferredLanguage($allowedLocales), 0, 5); // e.g., "zh-CN"
                $browserLocale = in_array($browserLocale, $allowedLocales) ? $browserLocale : substr($browserLocale, 0, 2);

                // Priority 4: fallback to config('app.locale')
                $locale = in_array($browserLocale, $allowedLocales) ? $browserLocale : config('app.locale');
            }
        }

        // Store locale in session so it persists
        Session::put('locale', $locale);

        // Set application locale
        App::setLocale($locale);

        return $next($request);
    }
}
