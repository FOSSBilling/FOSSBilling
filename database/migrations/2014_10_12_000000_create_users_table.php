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
            $table->string('aid')->nullable(true)->default(null)->index(); // Alternative id for foreign systems
            $table->bigInteger('client_group_id')->nullable(true)->default(null)->index();
            $table->string('role', 30)->default('client');
            $table->string('auth_type')->nullable(true)->default(null);
            $table->string('email')->unique();
            $table->string('password')->nullable(true)->default(true);
            $table->string('salt')->nullable(true)->default(null);
            $table->string('status', 30)->nullable(true)->default('active');  // active, suspended, canceled
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('tax_exempt')->nullable(true)->default(0);
            $table->string('type', 100)->nullable(true)->default(null);
            $table->string('name');
            $table->string('gender', 20)->nullable(true)->default(null);
            $table->date('birthday')->nullable(true)->default(null);
            $table->string('phone_cc', 10)->nullable(true)->default(null);
            $table->string('phone', 100)->nullable(true)->default(null);
            $table->string('company', 100)->nullable(true)->default(null);
            $table->string('company_vat', 100)->nullable(true)->default(null);
            $table->string('company_number')->nullable(true)->default(null);
            $table->string('address_1', 100)->nullable(true)->default(null);
            $table->string('address_2', 100)->nullable(true)->default(null);
            $table->string('city', 100)->nullable(true)->default(null);
            $table->string('state', 100)->nullable(true)->default(null);
            $table->string('postcode', 100)->nullable(true)->default(null);
            $table->string('country', 100)->nullable(true)->default(null);
            $table->string('document_type', 100)->nullable(true)->default(null);
            $table->string('document_nr', 20)->nullable(true)->default(null);
            $table->text('notes')->nullable(true)->default(null);
            $table->string('currency', 10)->default('USD');
            $table->string('lang', 5)->nullable(true)->default(null);
            $table->string('ip', 45)->nullable(true)->default(null);
            $table->string('api_token', 128)->nullable(true)->default(null);
            $table->string('referred_by')->nullable(true)->default(null);
            $table->text('custom_1')->nullable(true)->default(null);
            $table->text('custom_2')->nullable(true)->default(null);
            $table->text('custom_3')->nullable(true)->default(null);
            $table->text('custom_4')->nullable(true)->default(null);
            $table->text('custom_5')->nullable(true)->default(null);
            $table->text('custom_6')->nullable(true)->default(null);
            $table->text('custom_7')->nullable(true)->default(null);
            $table->text('custom_8')->nullable(true)->default(null);
            $table->text('custom_9')->nullable(true)->default(null);
            $table->text('custom_10')->nullable(true)->default(null);
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
