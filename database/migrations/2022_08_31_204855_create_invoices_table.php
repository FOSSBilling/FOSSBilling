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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable()->index();
            $table->string('serie', 50)->nullable()->default('NULL');
            $table->string('nr')->nullable()->default('NULL');
            $table->string('hash')->nullable()->default('NULL');
            $table->string('currency', 25)->nullable()->default('NULL');
            $table->decimal('currency_rate', 13, 6)->nullable()->default(0);
            $table->double("credit", 18, 2)->nullable()->default(0);
            $table->double("base_income", 18, 2)->nullable()->default(0);
            $table->double("base_refund", 18, 2)->nullable()->default(0);
            $table->double("refund", 18, 2)->nullable()->default(0);
            $table->text('notes');
            $table->text('text_1');
            $table->text('text_2');
            $table->string('status', 50)->default('unpaid');
            $table->string('seller_company')->nullable()->default('NULL');
            $table->string('seller_company_vat')->nullable()->default('NULL');
            $table->string('seller_company_number')->nullable()->default('NULL');
            $table->string('seller_address')->nullable()->default('NULL');
            $table->string('seller_phone')->nullable()->default('NULL');
            $table->string('seller_email')->nullable()->default('NULL');
            $table->string('buyer_first_name')->nullable()->default('NULL');
            $table->string('buyer_last_name')->nullable()->default('NULL');
            $table->string('buyer_company')->nullable()->default('NULL');
            $table->string('buyer_company_vat')->nullable()->default('NULL');
            $table->string('buyer_company_number')->nullable()->default('NULL');
            $table->string('buyer_address')->nullable()->default('NULL');
            $table->string('buyer_city')->nullable()->default('NULL');
            $table->string('buyer_state')->nullable()->default('NULL');
            $table->string('buyer_country')->nullable()->default('NULL');
            $table->string('buyer_zip')->nullable()->default('NULL');
            $table->string('buyer_phone')->nullable()->default('NULL');
            $table->string('buyer_phone_cc')->nullable()->default('NULL');
            $table->string('buyer_email')->nullable()->default('NULL');
            $table->integer('gateway_id' )->nullable()->default('0');
            $table->tinyInteger('approved' )->default('0');
            $table->string('taxname')->nullable()->default('NULL');
            $table->string('taxrate', 35)->nullable()->default('NULL');
            $table->datetime('due_at')->nullable();
            $table->datetime('reminded_at')->nullable();
            $table->datetime('paid_at')->nullable();
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
        Schema::dropIfExists('invoices');
    }
};
