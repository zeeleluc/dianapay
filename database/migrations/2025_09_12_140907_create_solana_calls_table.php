<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solana_calls', function (Blueprint $table) {
            $table->id();
            $table->string('token_name');  // e.g., "The Codfather ($CODFATHER)"
            $table->string('token_address');  // e.g., "GKmU479vHHNDKHRxjCfpF8g1XLNFG2sAWNDFZtujNrnF"
            $table->string('market_currency')->default('SOL');  // e.g., "SOL"
            $table->integer('age_minutes')->nullable();  // e.g., 2 from "🌱2m"
            $table->integer('view_count')->nullable();  // e.g., 82 from "👁️82"
            $table->decimal('usd_price', 20, 10)->nullable();  // e.g., 0.00005572
            $table->decimal('market_cap', 20, 2)->nullable();  // e.g., 55700
            $table->decimal('volume_24h', 20, 2)->nullable();  // e.g., 140300
            $table->string('liquidity_pool')->nullable();  // e.g., "N/A"
            $table->decimal('price_change_1h_percent', 8, 2)->nullable();  // e.g., 588.00
            $table->integer('buy_count_1h')->nullable();  // e.g., 496 from "🅑 496"
            $table->integer('sell_count_1h')->nullable();  // e.g., 348 from "Ⓢ 348"
            $table->string('all_time_high')->nullable();  // e.g., "N/A"
            $table->string('socials')->nullable();  // e.g., "𝕏 • Web • [about]"
            $table->integer('socials_age_minutes')->nullable();  // e.g., 2 from "[2m]"
            $table->decimal('top_10_holders_percent', 5, 2)->nullable();  // e.g., 58.00
            $table->string('top_holders_distribution')->nullable();  // e.g., "36.1|3.5|3.1|3.0|2.3" from "TH"
            $table->string('dev_sold')->nullable();  // e.g., "?"
            $table->string('dex_paid_status')->nullable();  // e.g., "🔴 [info]"
            $table->string('badges')->nullable();  // e.g., "DEF•DS•GT•MOB•EXP•𝕏s"
            $table->string('exchanges')->nullable();  // e.g., "AXI•TRO•PHO•GM•NEO•BLO MAE•PDR•OKX•BAN•BNK•PEP"
            $table->string('call_type')->nullable();  // e.g., "First Call 🚀 SOLANA100XCALL • Premium Signals @ $55.7K"
            $table->string('additional_notes')->nullable();  // e.g., "🦖 NEW: Trenches Interface!"
            $table->timestamps();  // created_at, updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solana_calls');
    }
};
