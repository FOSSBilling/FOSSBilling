<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string("name");
            $table->enum("status", ['active', 'suspended', 'canceled']);
            $table->string("billing_email");
            $table->string("abuse_email");
            $table->boolean("tax_exempt")->default(0);
            $table->string("type");
            $table->string("phone_cc");
            $table->string("phone");
            $table->string("company");
            $table->string("company_vat");
            $table->string("company_coc");
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
            $table->string("api_token");
            $table->string("referred_by");
            $table->json("custom")->nullable();

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
