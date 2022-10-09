<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gateway');
            $table->text('accepted_currencies')->nullable(true)->default(null);
            $table->boolean('enabled')->default('1');
            $table->boolean('allow_single')->default('1');
            $table->boolean('allow_recurrent')->default('1');
            $table->boolean('test_mode')->default('0');
            $table->text('config')->nullable(true)->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_gateways');
    }
};
