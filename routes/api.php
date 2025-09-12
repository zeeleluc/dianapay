<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolanaCallOrderController;

Route::post('/solana-call-orders', [SolanaCallOrderController::class, 'store']);
