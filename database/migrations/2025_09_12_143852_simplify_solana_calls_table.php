<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            // Drop unused columns (keep only the 10 needed)
            $table->dropColumn([
                'view_count',
                'price_change_1h_percent',
                'buy_count_1h',
                'sell_count_1h',
                'socials',
                'socials_age_minutes',
                'top_holders_distribution',
                'badges',
                'exchanges',
                'call_type',
                'additional_notes',
                'fresh_holders',
                'supply',
                'dev_tokens',
                'market_currency',  // Optional drop if always 'SOL'
            ]);

            // Ensure remaining columns are nullable and adjust types
            $table->string('token_name')->nullable()->change();
            $table->string('token_address')->nullable()->change();
            $table->integer('age_minutes')->nullable()->change();
            $table->decimal('market_cap', 20, 2)->nullable()->change();
            $table->decimal('volume_24h', 20, 2)->nullable()->change();
            $table->decimal('liquidity_pool', 20, 2)->nullable()->change();
            $table->decimal('all_time_high', 20, 2)->nullable()->change();
            $table->decimal('top_10_holders_percent', 5, 2)->nullable()->change();
            $table->boolean('dev_sold')->default(false)->nullable()->change();  // Boolean, default false
            $table->boolean('dex_paid_status')->default(false)->nullable()->change();  // Boolean, default false
        });
    }

    public function down(): void
    {
        // Revert: Add back dropped columns (adjust as needed)
        Schema::table('solana_calls', function (Blueprint $table) {
            $table->integer('view_count')->nullable();
            // ... add back others with appropriate types
        });
    }
};
