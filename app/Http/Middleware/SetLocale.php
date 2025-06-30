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

        // Use session value if already set
        $locale = Session::get('locale');

        if (!$locale) {
            // Try to detect from browser
            $browserLocale = substr($request->getPreferredLanguage($allowedLocales), 0, 5); // e.g., "zh-CN"
            $browserLocale = in_array($browserLocale, $allowedLocales) ? $browserLocale : substr($browserLocale, 0, 2);

            // Fallback to English
            $locale = in_array($browserLocale, $allowedLocales) ? $browserLocale : config('app.locale');

            Session::put('locale', $locale);
        }

        App::setLocale($locale);

        return $next($request);
    }
}
