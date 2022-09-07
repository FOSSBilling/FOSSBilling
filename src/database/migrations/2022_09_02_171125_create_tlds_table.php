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
        Schema::create('tlds', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('tld_registrar_id')->nullable()->index();
            $table->string('tld',15)->nullable()->default('NULL')->unique();
            $table->decimal('price_registration',18,2)->default('0.00');
            $table->decimal('price_renew',18,2)->default('0.00');
            $table->decimal('price_transfer',18,2)->default('0.00');
            $table->tinyInteger('allow_register')->nullable();
            $table->tinyInteger('allow_transfer')->nullable();
            $table->tinyInteger('active');
            $table->tinyInteger('min_years')->nullable();
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
        Schema::dropIfExists('tlds');
    }
};
