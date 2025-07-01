<?php

namespace App\Http\Controllers;

use App\Models\AnonymousPaymentRequest;
use App\Services\QrCodeUploaderService;

class PublicAnonymousPaymentController extends Controller
{

    /**
     * Show the anonymous payment request by UUID.
     */
    public function show(string $uuid)
    {
        $paymentRequest = AnonymousPaymentRequest::where('identifier', $uuid)->first();

        if (!$paymentRequest) {
            abort(404, translate('Payment request not found.'));
        }

        return view('public.anonymous-payment-request.show', [
            'paymentRequest' => $paymentRequest,
        ]);
    }

    /**
     * Show the anonymous payment request by UUID in request format.
     */
    public function request(string $uuid)
    {
        $paymentRequest = AnonymousPaymentRequest::where('identifier', $uuid)->first();

        if (!$paymentRequest) {
            abort(404, translate('Payment request not found.'));
        }

        return view('public.anonymous-payment-request.request', [
            'paymentRequest' => $paymentRequest,
            'showUrl' => route('payment.anonymous.show', ['uuid' => $paymentRequest->identifier]),
            'createUrl' => route('payment.anonymous.create'),
            'qrUrl' => (new QrCodeUploaderService())->getOrCreateAnonymousPaymentRequestQR($paymentRequest),
        ]);
    }
}
