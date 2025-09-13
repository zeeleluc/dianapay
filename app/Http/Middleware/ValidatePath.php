<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ValidatePath
{
    public function handle(Request $request, Closure $next)
    {
        $path = trim($request->path(), '/'); // e.g. "", "en", "en/about"

        // ✅ Bypass list: routes that should not be validated
        $bypass = ['pincode', 'sniper'];

        foreach ($bypass as $ignore) {
            if ($request->is($ignore) || $request->is("{$ignore}/*")) {
                return $next($request);
            }
        }

        $segments = $path === '' ? [] : explode('/', $path);
        $allowedLocales = config('locales.allowed', ['en']);

        // Case: 0 segments — allow
        if (count($segments) === 0) {
            return $next($request);
        }

        // Case: 1 segment — must be valid locale
        if (count($segments) === 1) {
            if (!in_array($segments[0], $allowedLocales)) {
                return Redirect::to('/', 301);
            }
        }

        // ✅ Case: 2 segments — if both are valid locales, abort
        if (count($segments) >= 2) {
            [$first, $second] = $segments;
            if (in_array($first, $allowedLocales) && in_array($second, $allowedLocales)) {
                return Redirect::to('/', 301);
            }
        }

        return $next($request);
    }
}
