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
        Schema::create('payout_configs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('merchant_id');
            $table->string('test_paymentUrl');
            $table->string('live_paymentUrl');
            $table->string('appId');
            $table->string('returnUrl');
            $table->string('callBackUrl');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_configs');
    }
};
