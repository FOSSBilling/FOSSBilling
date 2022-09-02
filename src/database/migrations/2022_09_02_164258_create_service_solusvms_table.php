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
        Schema::create('service_solusvms', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('cluster_id')->nullable();
            $table->bigInteger('client_id')->nullable()->index();
            $table->string('vserverid')->nullable()->default('NULL');
            $table->string('virtid')->nullable()->default('NULL');
            $table->string('nodeid')->nullable()->default('NULL');
            $table->string('type')->nullable()->default('NULL');
            $table->string('node')->nullable()->default('NULL');
            $table->string('nodegroup')->nullable()->default('NULL');
            $table->string('hostname')->nullable()->default('NULL');
            $table->string('rootpassword')->nullable()->default('NULL');
            $table->string('username')->nullable()->default('NULL');
            $table->string('plan')->nullable()->default('NULL');
            $table->string('template')->nullable()->default('NULL');
            $table->string('ips')->nullable()->default('NULL');
            $table->string('hvmt')->nullable()->default('NULL');
            $table->string('custommemory')->nullable()->default('NULL');
            $table->string('customdiskspace')->nullable()->default('NULL');
            $table->string('custombandwidth')->nullable()->default('NULL');
            $table->string('customcpu')->nullable()->default('NULL');
            $table->string('customextraip')->nullable()->default('NULL');
            $table->string('issuelicense')->nullable()->default('NULL');
            $table->string('mainipaddress')->nullable()->default('NULL');
            $table->string('extraipaddress')->nullable()->default('NULL');
            $table->string('consoleuser')->nullable()->default('NULL');
            $table->string('consolepassword')->nullable()->default('NULL');
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
        Schema::dropIfExists('service_solusvms');
    }
};
