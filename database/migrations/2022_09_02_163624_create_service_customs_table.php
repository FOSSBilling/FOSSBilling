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
        Schema::create('service_customs', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable()->index();
            $table->string('plugin')->nullable()->default('NULL');
            $table->text('plugin_config');
            $table->text('f1');
            $table->text('f2');
            $table->text('f3');
            $table->text('f4');
            $table->text('f5');
            $table->text('f6');
            $table->text('f7');
            $table->text('f8');
            $table->text('f9');
            $table->text('f10');
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
        Schema::dropIfExists('service_customs');
    }
};
