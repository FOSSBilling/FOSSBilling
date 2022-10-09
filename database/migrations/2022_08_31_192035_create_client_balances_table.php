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
        Schema::create('client_balances', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("client_id")->index();
            $table->string("type");
            $table->string("rel_id");
            $table->decimal("amount",18,2)->default(0.00);
            $table->text("description");
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
        Schema::dropIfExists('client_balances');
    }
};
