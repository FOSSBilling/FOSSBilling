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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id',)->nullable()->index();
            $table->bigInteger('pay_gateway_id',)->nullable()->index();
            $table->string('sid')->nullable()->default('NULL');
            $table->string('rel_type', 100)->nullable()->default('NULL');
            $table->bigInteger('rel_id',)->nullable();
            $table->string('period')->nullable()->default('NULL');
            $table->double("amount", 18, 2)->nullable()->default(0);
            $table->string('currency', 50)->nullable()->default(0);
            $table->string('status')->nullable()->default('NULL');
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
        Schema::dropIfExists('subscriptions');
    }
};
