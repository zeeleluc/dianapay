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
            $table->string('fresh_holders')->nullable();
            $table->string('supply')->nullable();
            $table->string('dev_tokens')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            //
        });
    }
};
