<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_call_unrealized_profits', function (Blueprint $table) {
            $table->decimal('buy_market_cap', 20, 6)->nullable()->after('unrealized_profit');
            $table->decimal('current_market_cap', 20, 6)->nullable()->after('buy_market_cap');
        });
    }

    public function down(): void
    {
        Schema::table('solana_call_unrealized_profits', function (Blueprint $table) {
            $table->dropColumn(['buy_market_cap', 'current_market_cap']);
        });
    }
};
