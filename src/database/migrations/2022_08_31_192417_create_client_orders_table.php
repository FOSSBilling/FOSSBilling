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
        Schema::create('client_orders', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("client_id")->index();
            $table->bigInteger("product_id")->index();
            $table->bigInteger("form_id")->index();
            $table->bigInteger("promo_id")->index();
            $table->boolean("promo_recurring")->default(0);
            $table->bigInteger("promo_used");
            $table->string("group_id");
            $table->boolean("group_master")->default(0);
            $table->string("invoice_option");
            $table->string("title");
            $table->string("currency");
            $table->bigInteger("unpaid_invoice_id");
            $table->bigInteger("service_id");
            $table->string("service_type");
            $table->string("period");
            $table->bigInteger("quantity",1);
            $table->string("unit");
            $table->decimal("price", 18,2);
            $table->decimal("discount");
            $table->string("status");
            $table->string("reason");
            $table->text("notes");
            $table->text("config");
            $table->string("referred_by");
            $table->dateTime("expires_at");
            $table->dateTime("activated_at");
            $table->dateTime("suspended_at");
            $table->dateTime("unsuspended_at");
            $table->dateTime("canceled_at");
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
        Schema::dropIfExists('client_orders');
    }
};
