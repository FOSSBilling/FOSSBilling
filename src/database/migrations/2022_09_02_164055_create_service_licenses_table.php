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
        Schema::create('service_licenses', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable()->index();
            $table->string('license_key')->nullable()->default('NULL')->unique();
            $table->boolean('validate_ip',1);
            $table->boolean('validate_host',1);
            $table->boolean('validate_path',1);
            $table->boolean('validate_version',1);
            $table->text('ips');
            $table->text('hosts');
            $table->text('paths');
            $table->text('versions');
            $table->text('config');
            $table->string('plugin')->nullable()->default('NULL');
            $table->datetime('checked_at')->nullable();
            $table->datetime('pinged_at')->nullable();
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
        Schema::dropIfExists('service_licenses');
    }
};
