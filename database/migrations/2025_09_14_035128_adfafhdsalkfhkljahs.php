<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->decimal('market_cap', 18, 2)->nullable()->after('amount_foreign')->comment('Market cap of token at the time of order');
        });
    }

    public function down(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->dropColumn('market_cap');
        });
    }
};
