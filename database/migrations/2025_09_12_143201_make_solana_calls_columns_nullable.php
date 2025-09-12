<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            // Make all existing columns nullable
            $table->string('token_name')->nullable()->change();
            $table->string('token_address')->nullable()->change();
            $table->string('market_currency')->nullable()->change();
            $table->integer('age_minutes')->nullable()->change();
            $table->integer('view_count')->nullable()->change();
            $table->decimal('usd_price', 20, 10)->nullable()->change();
            $table->decimal('market_cap', 20, 2)->nullable()->change();
            $table->decimal('volume_24h', 20, 2)->nullable()->change();
            $table->string('liquidity_pool')->nullable()->change();
            $table->decimal('price_change_1h_percent', 8, 2)->nullable()->change();
            $table->integer('buy_count_1h')->nullable()->change();
            $table->integer('sell_count_1h')->nullable()->change();
            $table->string('all_time_high')->nullable()->change();
            $table->string('socials')->nullable()->change();
            $table->integer('socials_age_minutes')->nullable()->change();
            $table->decimal('top_10_holders_percent', 5, 2)->nullable()->change();
            $table->string('top_holders_distribution')->nullable()->change();
            $table->string('dev_sold')->nullable()->change();
            $table->string('dex_paid_status')->nullable()->change();
            $table->string('badges')->nullable()->change();
            $table->string('exchanges')->nullable()->change();
            $table->string('call_type')->nullable()->change();
            $table->string('additional_notes')->nullable()->change();
            $table->string('fresh_holders')->nullable()->change();
            $table->string('supply')->nullable()->change();
            $table->string('dev_tokens')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Revert (make non-nullable again, but adjust defaults as needed)
        Schema::table('solana_calls', function (Blueprint $table) {
            // Example revert (omit if not needed)
            $table->string('token_name')->nullable(false)->change();
            // ... repeat for all
        });
    }
};
