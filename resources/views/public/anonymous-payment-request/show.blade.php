@extends('layouts.app')

@section('content')
    <div class="max-w-xl mx-auto p-6 bg-gray-100 rounded-lg shadow">
        <h1 class="text-2xl font-bold mb-4">Anonymous Payment Request</h1>

        <p><strong>Identifier (UUID):</strong> {{ $paymentRequest->identifier }}</p>
        <p><strong>Fiat:</strong> {{ strtoupper($paymentRequest->fiat) }}</p>
        <p><strong>Amount (minor unit):</strong> {{ $paymentRequest->amount_minor }}</p>
        <p><strong>To Wallet:</strong> {{ $paymentRequest->to_wallet }}</p>
        <p><strong>Description:</strong> {{ $paymentRequest->description }}</p>
        <p><strong>Crypto Token:</strong> {{ $paymentRequest->crypto ?? 'N/A' }}</p>
        <p><strong>Rate:</strong> {{ $paymentRequest->rate ?? 'N/A' }}</p>
        <p><strong>Transaction TX:</strong> {{ $paymentRequest->transaction_tx ?? 'N/A' }}</p>
        <p><strong>Status:</strong> {{ ucfirst($paymentRequest->status) }}</p>
        <p><strong>Created At:</strong> {{ $paymentRequest->created_at->toDayDateTimeString() }}</p>

        @if($paymentRequest->paid_at)
            <p><strong>Paid At:</strong> {{ $paymentRequest->paid_at->toDayDateTimeString() }}</p>
        @endif
    </div>
@endsection
