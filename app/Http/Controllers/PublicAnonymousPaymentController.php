<?php

namespace App\Http\Controllers;

use App\Models\AnonymousPaymentRequest;
use App\Services\QrCodeUploaderService;

class PublicAnonymousPaymentController extends Controller
{

    /**
     * Show the anonymous payment request by UUID in request format.
     */
    public function request(string $locale, string $uuid)
    {
        $paymentRequest = AnonymousPaymentRequest::where('identifier', $uuid)->first();

        if (!$paymentRequest) {
            abort(404, translate('Payment request not found.'));
        }

        return view('public.anonymous-payment-request.request', [
            'paymentRequest' => $paymentRequest,
            'showUrl' => route('payment.anonymous.show', [
                'locale' => get_locale(),
                'uuid' => $paymentRequest->identifier
            ]),
            'createUrl' => route('payment.anonymous.create', get_locale()),
            'qrUrl' => (new QrCodeUploaderService())->getOrCreateAnonymousPaymentRequestQR($paymentRequest),
        ]);
    }
}
