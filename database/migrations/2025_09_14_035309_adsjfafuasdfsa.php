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
        Schema::table('solana_calls', function (Blueprint $table) {
            $table->dropColumn('all_time_high');
            $table->dropColumn('top_10_holders_percent');
        });
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->dropColumn('market_cap');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
