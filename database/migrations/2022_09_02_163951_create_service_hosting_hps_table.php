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
        Schema::create('service_hosting_hps', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->default('NULL');
            $table->string('quota',50)->nullable()->default('NULL');
            $table->string('bandwidth',50)->nullable()->default('NULL');
            $table->string('max_ftp',50)->nullable()->default('NULL');
            $table->string('max_sql',50)->nullable()->default('NULL');
            $table->string('max_pop',50)->nullable()->default('NULL');
            $table->string('max_sub',50)->nullable()->default('NULL');
            $table->string('max_park',50)->nullable()->default('NULL');
            $table->string('max_addon',50)->nullable()->default('NULL');
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
        Schema::dropIfExists('service_hosting_hps');
    }
};
