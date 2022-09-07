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
        Schema::create('service_hostings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable()->index();
            $table->bigInteger('service_hosting_server_id')->nullable()->index();
            $table->bigInteger('service_hosting_hp_id')->nullable()->index();
            $table->string('sld')->nullable()->default('NULL');
            $table->string('tld')->nullable()->default('NULL');
            $table->string('ip',45)->nullable()->default('NULL');
            $table->string('username')->nullable()->default('NULL');
            $table->string('pass')->nullable()->default('NULL');
            $table->tinyInteger('reseller')->nullable();
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
        Schema::dropIfExists('service_hostings');
    }
};
