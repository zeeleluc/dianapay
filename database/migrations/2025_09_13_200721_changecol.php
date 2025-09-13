<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // In a new migration file: php artisan make:migration increase_solana_call_orders_error_column
    public function up()
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->text('error')->nullable()->change(); // Or string(1000) if you prefer
        });
    }
    public function down()
    {
        Schema::table('solana_call_orders', function (Blueprint $table) {
            $table->string('error', 255)->nullable()->change();
        });
    }
};
