<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('crypto', 10)->comment('Cryptocurrency, e.g., USDT, USDC, ETH, BRETT');
            $table->string('fiat', 10)->comment('Fiat currency, e.g., USD, EUR');
            $table->string('blockchain');
            $table->decimal('rate', 18, 8)->comment('Exchange rate (crypto to fiat, e.g., 1 BRETT = X EUR)');
            $table->dateTime('recorded_at')->comment('Timestamp of the rate (date, hour, minute)');
            $table->timestamps();

            // Unique constraint to prevent duplicate rates for the same crypto/fiat pair and timestamp
            $table->unique(['crypto', 'fiat', 'blockchain', 'recorded_at'], 'currency_rates_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
