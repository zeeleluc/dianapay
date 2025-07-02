<?php

namespace App\Services;

use App\Models\AnonymousPaymentRequest;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeUploaderService
{
    /**
     * Generates a QR code image from a URL and uploads it to DigitalOcean Spaces.
     *
     * @param string $url  The URL to encode in the QR code.
     * @param string $path The full destination path in the DO Spaces bucket (e.g. 'qr/test.png').
     * @return string|null Public URL to the uploaded QR image or null on failure.
     */
    public function upload(string $url, string $path): ?string
    {
        try {
            // Generate QR code image in PNG format
            $pngData = QrCode::format('png')
                ->size(400)
                ->margin(2)
                ->errorCorrection('H')
                ->color(0, 0, 0)
                ->backgroundColor(255, 255, 255)
//                ->merge(public_path('images/logo.png'), 0.2, true)
                ->generate($url);

            // Upload to Spaces with public visibility
            Storage::disk('spaces')->put($path, $pngData, 'public');

            // Return public URL
            return Storage::disk('spaces')->url($path);
        } catch (\Throwable $e) {
            \Log::error('QR code upload failed: ' . $e->getMessage());
            return null;
        }
    }

    public function getOrCreateAnonymousPaymentRequestQR(AnonymousPaymentRequest $anonymousPaymentRequest): ?string
    {
        $path = 'qrs-anonymous-payment-requests/' . $anonymousPaymentRequest->identifier . '.png';
        $disk = Storage::disk('spaces');

        if ($anonymousPaymentRequest->has_qr_image) {
            return $disk->url($path);
        }

        $result = $this->upload(
            route('payment.anonymous.show', [
                'locale' => get_locale(),
                'uuid' => $anonymousPaymentRequest->identifier
            ]),
            $path
        );

        if ($result) {
            $anonymousPaymentRequest->has_qr_image = true;
            $anonymousPaymentRequest->save();

            return $result;
        }

        return null;
    }
}
