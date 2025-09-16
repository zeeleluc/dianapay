<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            $table->decimal('previous_unrealized_profits', 18, 8)->default(0);
        });
    }

    public function down()
    {
        Schema::table('solana_calls', function (Blueprint $table) {
            $table->dropColumn('previous_unrealized_profits');
        });
    }
};
