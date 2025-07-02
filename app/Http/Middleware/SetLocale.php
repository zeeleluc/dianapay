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
        Log::info('SetLocale middleware', ['path' => $request->path(), 'locale' => $request->route('locale')]);

        // Bypass locale handling for Livewire requests to prevent 419 errors
        if ($request->is('livewire/*')) {
            return $next($request);
        }

        $allowedLocales = config('locales.allowed', ['en']);
        $locale = $request->route('locale');

        // If locale present but invalid, try to fix or abort
        if ($locale && !in_array($locale, $allowedLocales)) {
            $locale = Session::get('locale', config('app.locale'));
            if (!in_array($locale, $allowedLocales)) {
                $browserLocale = substr($request->getPreferredLanguage($allowedLocales), 0, 5);
                $locale = in_array($browserLocale, $allowedLocales) ? $browserLocale : config('app.locale');
            }
            $path = preg_replace('/^[^\/]+\//', '', $request->path()) ?: '';
            $newPath = "/{$locale}/{$path}";

            $testRequest = $request->duplicate(null, null, null, null, null, ['REQUEST_URI' => $newPath]);
            try {
                $route = app('router')->getRoutes()->match($testRequest);
                if ($route && !$route->isFallback) {
                    return Redirect::to($newPath, 301);
                }
            } catch (NotFoundHttpException $e) {
                abort(404);
            }
        }

        // If no locale present, redirect to a locale prefixed URL
        if (!$locale) {
            $locale = Session::get('locale', config('app.locale'));
            if (!in_array($locale, $allowedLocales)) {
                $browserLocale = substr($request->getPreferredLanguage($allowedLocales), 0, 5);
                $locale = in_array($browserLocale, $allowedLocales) ? $browserLocale : config('app.locale');
            }
            $path = $request->path() === '/' ? '' : $request->path();
            $newPath = "/{$locale}/{$path}";

            $testRequest = $request->duplicate(null, null, null, null, null, ['REQUEST_URI' => $newPath]);
            try {
                $route = app('router')->getRoutes()->match($testRequest);
                if ($route && !$route->isFallback) {
                    return Redirect::to($newPath, 301);
                }
            } catch (NotFoundHttpException $e) {
                abort(404);
            }
        }

        // Set locale in session and app
        Session::put('locale', $locale);
        App::setLocale($locale);

        return $next($request);
    }
}
