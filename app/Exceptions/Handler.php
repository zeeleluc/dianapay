<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class Handler extends ExceptionHandler
{
    // Add this if missing
    protected $dontReport = [];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }


    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenMismatchException) {
            return redirect()->back()
                ->withErrors([
                    'session' => 'Your session has expired. Please refresh and try again.',
                ]);
        }

        return parent::render($request, $exception);
    }
}
