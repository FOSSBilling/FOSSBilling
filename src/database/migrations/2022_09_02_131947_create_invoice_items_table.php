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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('invoice_id');
            $table->string('type',100)->nullable()->default('NULL');
            $table->text('rel_id');
            $table->string('task',100)->nullable()->default('NULL');
            $table->string('status',100)->nullable()->default('NULL');
            $table->string('title')->nullable()->default('NULL');
            $table->string('period',10)->nullable()->default('NULL');
            $table->bigInteger('quantity',)->nullable()->default(0);
            $table->string('unit',100)->nullable()->default('NULL');
            $table->double("price",18,2)->nullable()->default(0);
            $table->tinyInteger('charged',)->default('0');
            $table->tinyInteger('taxed',)->default('0');
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
        Schema::dropIfExists('invoice_items');
    }
};
