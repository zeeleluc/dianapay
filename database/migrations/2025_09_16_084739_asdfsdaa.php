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
            $table->text('reason_buy')->nullable()->after('strategy');
            $table->text('reason_sell')->nullable()->after('reason_buy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            $table->dropColumn(['reason_buy', 'reason_sell']);
        });
    }
};
