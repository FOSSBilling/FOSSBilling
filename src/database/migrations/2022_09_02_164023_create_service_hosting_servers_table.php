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
        Schema::create('service_hosting_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name',100)->nullable()->default('NULL');
            $table->string('ip',45)->nullable()->default('NULL');
            $table->string('hostname',100)->nullable()->default('NULL');
            $table->text('assigned_ips');
            $table->string('status_url')->nullable()->default('NULL');
            $table->tinyInteger('active')->nullable()->default(1);
            $table->bigInteger('max_accounts')->nullable();
            $table->string('ns1',100)->nullable()->default('NULL');
            $table->string('ns2',100)->nullable()->default('NULL');
            $table->string('ns3',100)->nullable()->default('NULL');
            $table->string('ns4',100)->nullable()->default('NULL');
            $table->string('manager',100)->nullable()->default('NULL');
            $table->text('username');
            $table->text('password');
            $table->text('accesshash');
            $table->string('port',20)->nullable()->default('NULL');
            $table->text('config');
            $table->tinyInteger('secure')->nullable();
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
        Schema::dropIfExists('service_hosting_servers');
    }
};
