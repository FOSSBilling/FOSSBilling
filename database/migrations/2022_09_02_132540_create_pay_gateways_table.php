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
        Schema::create('pay_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->default('NULL');
            $table->string('gateway')->nullable()->default('NULL');
            $table->text('accepted_currencies');
            $table->tinyInteger('enabled',)->default('1');
            $table->tinyInteger('allow_single',)->default('1');
            $table->tinyInteger('allow_recurrent',)->default('1');
            $table->tinyInteger('test_mode',)->default('0');
            $table->text('config');
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
        Schema::dropIfExists('pay_gateways');
    }
};
