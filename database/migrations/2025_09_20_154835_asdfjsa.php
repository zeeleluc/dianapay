<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->decimal('price_usd', 18, 8)->nullable()->after('amount_foreign')->comment('Price of token in USD at the time of order');
        });
    }

    public function down(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->dropColumn('price_usd');
        });
    }
};
