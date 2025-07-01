<?php

namespace App\Http\Controllers;

use App\Models\AnonymousPaymentRequest;

class PublicAnonymousPaymentController extends Controller
{

    /**
     * Show the anonymous payment request by UUID.
     */
    public function show(string $uuid)
    {
        $paymentRequest = AnonymousPaymentRequest::where('identifier', $uuid)->first();

        if (!$paymentRequest) {
            abort(404, 'Payment request not found.');
        }

        return view('public.anonymous-payment-request.show', [
            'paymentRequest' => $paymentRequest,
        ])->layout('layouts.homepage');
    }
}
