<?php

namespace App\Http\Controllers;

use App\Models\SolanaCallOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\SlackNotifier;

class SolanaCallOrderController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Incoming Solana order: ' . $request->getContent());
        Log::info('JSON decoded: ' . json_encode($request->json()->all()));

        try {
            $data = $request->validate([
                // 'solana_call_id' => 'required|exists:solana_calls,id',
                'solana_call_id' => 'required|integer',
                'type' => 'required|in:buy,sell,failed',
                'amount_foreign' => 'nullable|numeric',
                'amount_sol' => 'nullable|numeric',
                'dex_used' => 'nullable|string',
                'error' => 'nullable|string',
                'tx_signature' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = json_encode($e->errors());
            Log::error('Validation failed: ' . $errors);
            SlackNotifier::error("❌ Validation failed for SolanaCallOrder: {$errors}");
            return response()->json(['errors' => $e->errors()], 422);
        }

        try {
            $order = SolanaCallOrder::create($data);

            $msg = "✅ New SolanaCallOrder created: ID {$order->id}, Call {$order->solana_call_id}, Type: {$order->type}, SOL: {$order->amount_sol}, Foreign: {$order->amount_foreign}, DEX: {$order->dex_used}";
            SlackNotifier::success($msg);
            Log::info($msg);

            return response()->json($order, 201);

        } catch (\Throwable $e) {
            $msg = "❌ Failed to create SolanaCallOrder: " . $e->getMessage();
            Log::error($msg);
            SlackNotifier::error($msg);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
