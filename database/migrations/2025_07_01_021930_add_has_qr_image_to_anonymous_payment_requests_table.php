<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anonymous_payment_requests', function (Blueprint $table) {
            $table->boolean('has_qr_image')->default(false)->after('accepted_crypto');
        });
    }

    public function down(): void
    {
        Schema::table('anonymous_payment_requests', function (Blueprint $table) {
            $table->dropColumn('has_qr_image');
        });
    }
};
