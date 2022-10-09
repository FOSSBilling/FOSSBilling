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
        Schema::create('service_domains', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable()->index();
            $table->bigInteger('tld_registrar_id')->nullable()->index();
            $table->string('sld')->nullable()->default('NULL');
            $table->string('tld',100)->nullable()->default('NULL');
            $table->string('ns1')->nullable()->default('NULL');
            $table->string('ns2')->nullable()->default('NULL');
            $table->string('ns3')->nullable()->default('NULL');
            $table->string('ns4')->nullable()->default('NULL');
            $table->integer('period')->nullable();
            $table->integer('privacy')->nullable();
            $table->tinyInteger('locked')->default(1);
            $table->string('transfer_code')->nullable()->default('NULL');
            $table->string('action',30)->nullable()->default('NULL');
            $table->string('contact_email')->nullable()->default('NULL');
            $table->string('contact_company')->nullable()->default('NULL');
            $table->string('contact_first_name')->nullable()->default('NULL');
            $table->string('contact_last_name')->nullable()->default('NULL');
            $table->string('contact_address1')->nullable()->default('NULL');
            $table->string('contact_address2')->nullable()->default('NULL');
            $table->string('contact_city')->nullable()->default('NULL');
            $table->string('contact_state')->nullable()->default('NULL');
            $table->string('contact_postcode')->nullable()->default('NULL');
            $table->string('contact_country')->nullable()->default('NULL');
            $table->string('contact_phone_cc')->nullable()->default('NULL');
            $table->string('contact_phone')->nullable()->default('NULL');
            $table->text('details');
            $table->datetime('synced_at')->nullable();
            $table->datetime('registered_at')->nullable();
            $table->datetime('expires_at')->nullable();
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
        Schema::dropIfExists('service_domains');
    }
};
