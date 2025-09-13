<?php

// routes/api.php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SolanaCallOrderController;
use App\Http\Controllers\LogController;

Route::post('/solana-call-orders', [SolanaCallOrderController::class, 'store']);
Route::post('/logs', [LogController::class, 'store']);
