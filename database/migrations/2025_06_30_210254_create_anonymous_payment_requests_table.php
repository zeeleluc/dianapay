<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnonymousPaymentRequestsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('anonymous_payment_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('identifier');
            $table->string('fiat', 3);
            $table->bigInteger('amount_minor'); // smallest currency unit (cents, yen, pence, etc.)

            // Wallet fields for different chains
            $table->string('to_wallet_evm', 64)->nullable();
            $table->string('to_wallet_bitcoin', 64)->nullable();
            $table->string('to_wallet_xrp', 64)->nullable();
            $table->string('to_wallet_solana', 64)->nullable();
            $table->string('to_wallet_cardano', 64)->nullable();
            $table->string('to_wallet_algorand', 64)->nullable();
            $table->string('to_wallet_stellar', 64)->nullable();
            $table->string('to_wallet_tezos', 64)->nullable();

            $table->text('description');
            $table->text('accepted_crypto');
            $table->string('crypto', 16)->nullable();
            $table->decimal('rate', 18, 10)->nullable();
            $table->string('transaction_tx', 100)->nullable();
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('anonymous_payment_requests');
    }
}
