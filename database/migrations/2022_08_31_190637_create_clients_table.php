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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string("aid")->nullable()->comment("Alternative id for foreign systems")->index();
            $table->bigInteger("client_group_id")->nullable()->index();
            $table->string("role")->default("client")->comment("client");
            $table->string("auth_type");
            $table->string("email");
            $table->string("password");
            $table->string("salt");
            $table->enum("status",['active','suspended','canceled']);
            $table->boolean("email_approved",)->default(0);
            $table->boolean("tax_exempt")->default(0);
            $table->string("type");
            $table->string("first_name");
            $table->string("last_name");
            $table->string("gender");
            $table->date("birthday")->nullable();
            $table->string("phone_cc");
            $table->string("phone");
            $table->string("company");
            $table->string("company_vat");
            $table->string("company_number");
            $table->string("address_1");
            $table->string("address_2");
            $table->string("city");
            $table->string("state");
            $table->string("zipcode");
            $table->string("country");
            $table->string("document_type");
            $table->string("document_nr");
            $table->text("notes");
            $table->string("currency");
            $table->string("lang");
            $table->ipAddress("ip");
            $table->string("api_token");
            $table->string("referred_by");
            $table->text("custom_1")->nullable();
            $table->text("custom_2")->nullable();
            $table->text("custom_3")->nullable();
            $table->text("custom_4")->nullable();
            $table->text("custom_5")->nullable();
            $table->text("custom_6")->nullable();
            $table->text("custom_7")->nullable();
            $table->text("custom_8")->nullable();
            $table->text("custom_9")->nullable();
            $table->text("custom_10")->nullable();

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
        Schema::dropIfExists('clients');
    }
};
