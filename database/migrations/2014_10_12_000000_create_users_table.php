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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('type', ['client', 'staff', 'admin'])->default('client');
            $table->enum('status', ['active', 'suspended', 'canceled'])->default('active');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('phone', 10)->nullable();
            $table->text('notes')->nullable();
            $table->string('lang', 5)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('api_token', 128)->nullable();
            $table->bigInteger('referred_by')->unsigned()->nullable();
            $table->json('custom')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
