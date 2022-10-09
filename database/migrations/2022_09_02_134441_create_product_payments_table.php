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
        Schema::create('product_payments', function (Blueprint $table) {
            $table->id();
            $table->string('type',30)->nullable()->default('NULL');
            $table->decimal('once_price',18,2)->default('0.00');
            $table->decimal('once_setup_price',18,2)->default('0.00');
            $table->decimal('w_price',18,2)->default('0.00');
            $table->decimal('m_price',18,2)->default('0.00');
            $table->decimal('q_price',18,2)->default('0.00');
            $table->decimal('b_price',18,2)->default('0.00');
            $table->decimal('a_price',18,2)->default('0.00');
            $table->decimal('bia_price',18,2)->default('0.00');
            $table->decimal('tria_price',18,2)->default('0.00');
            $table->decimal('w_setup_price',18,2)->default('0.00');
            $table->decimal('m_setup_price',18,2)->default('0.00');
            $table->decimal('q_setup_price',18,2)->default('0.00');
            $table->decimal('b_setup_price',18,2)->default('0.00');
            $table->decimal('a_setup_price',18,2)->default('0.00');
            $table->decimal('bia_setup_price',18,2)->default('0.00');
            $table->decimal('tria_setup_price',18,2)->default('0.00');
            $table->tinyInteger('w_enabled')->default(1);
            $table->tinyInteger('m_enabled')->default(1);
            $table->tinyInteger('q_enabled')->default(1);
            $table->tinyInteger('b_enabled')->default(1);
            $table->tinyInteger('a_enabled')->default(1);
            $table->tinyInteger('bia_enabled')->default(1);
            $table->tinyInteger('tria_enabled')->default(1);
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
        Schema::dropIfExists('product_payments');
    }
};
