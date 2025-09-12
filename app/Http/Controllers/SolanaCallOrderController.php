<?php

// app/Http/Controllers/SolanaCallOrderController.php
namespace App\Http\Controllers;

use App\Models\SolanaCallOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SolanaCallOrderController extends Controller
{
    public function store(Request $request)
    {
        Log::info('Incoming Solana order: '.$request->getContent());
        Log::info('JSON decoded: '.json_encode($request->json()->all()));

        try {
            $data = $request->validate([
//            'solana_call_id' => 'required|exists:solana_calls,id',
                'solana_call_id' => 'required|integer',
                'type' => 'required|in:buy,sell,failed',
                'amount_foreign' => 'nullable|numeric',
                'amount_sol' => 'nullable|numeric',
                'dex_used' => 'nullable|string',
                'error' => 'nullable|string',
                'tx_signature' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed: '.json_encode($e->errors()));
            return response()->json(['errors'=>$e->errors()], 422);
        }


        Log::error('test');

        $order = SolanaCallOrder::create($data);

        return response()->json($order, 201);
    }
}
