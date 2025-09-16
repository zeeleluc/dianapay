<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solana_call_unrealized_profits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solana_call_id')
                ->constrained('solana_calls')
                ->onDelete('cascade');
            $table->decimal('unrealized_profit', 10, 2); // store % with 2 decimals
            $table->timestamps(); // includes created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solana_call_unrealized_profits');
    }
};
