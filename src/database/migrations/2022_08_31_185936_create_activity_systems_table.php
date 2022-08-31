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
        Schema::create('activity_systems', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger("priority");
            $table->bigInteger("admin_id")->nullable()->index();
            $table->bigInteger("client_id")->nullable()->index();
            $table->text("message");
            $table->ipAddress("ip");
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
        Schema::dropIfExists('activity_systems');
    }
};
