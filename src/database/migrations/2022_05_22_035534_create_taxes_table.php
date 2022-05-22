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
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('level')->nullable(true)->default('0');
            $table->string('name');
            $table->string('description')->nullable(true)->default(null);
            $table->string('country')->nullable(true)->default(null);
            $table->string('state')->nullable(true)->default(null);
            $table->string('taxrate');
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
        Schema::dropIfExists('taxes');
    }
};
