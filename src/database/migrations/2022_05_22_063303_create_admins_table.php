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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_group_id')->nullable(true)->default(1)->index();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('name');
            $table->string('signature')->nullable(true)->default(null);
            $table->boolean('protected')->nullable(true)->default(0);
            $table->string('status', 30)->nullable(true)->default('active');
            $table->string('api_token', 128)->nullable(true)->default(null);
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
        Schema::dropIfExists('admins');
    }
};
