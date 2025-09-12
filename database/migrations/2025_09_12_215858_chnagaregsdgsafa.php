<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            // Change 'type' to a simple string column
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            // Revert back to enum if needed (adjust values as per original)
            $table->enum('type', ['buy', 'sell', 'failed'])->change();
        });
    }
};
