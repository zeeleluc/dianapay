<?php

// app/Http/Middleware/PincodeMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class PincodeMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Session::get('pincode_validated')) {
            return $next($request);
        }

        return redirect()->route('pincode.form');
    }
}
