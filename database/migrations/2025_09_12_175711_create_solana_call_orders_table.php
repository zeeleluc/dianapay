<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solana_call_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solana_call_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['buy', 'sell']); // order type

            // amounts
            $table->decimal('amount_foreign', 30, 8)->nullable(); // token amount
            $table->decimal('amount_sol', 20, 9)->nullable();     // SOL amount

            // execution info
            $table->string('dex_used')->nullable();   // Raydium / PumpSwap / Jupiter
            $table->string('error')->nullable();      // if failed

            // tx
            $table->string('tx_signature')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solana_call_orders');
    }
};
