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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_category_id')->nullable()->index();
            $table->bigInteger('product_payment_id')->nullable()->index();
            $table->bigInteger('form_id')->nullable()->index();
            $table->string('title')->nullable()->default('NULL');
            $table->string('slug')->nullable()->default('NULL')->unique();
            $table->text('description');
            $table->string('unit',50)->default('product');
            $table->tinyInteger('active')->default('1');
            $table->string('status',50)->default('enabled');
            $table->tinyInteger('hidden')->default('0');
            $table->tinyInteger('is_addon')->default('0');
            $table->string('setup',50)->default('after_payment');
            $table->text('addons');
            $table->string('icon_url')->nullable()->default('NULL');
            $table->tinyInteger('allow_quantity_select')->default(0);
            $table->tinyInteger('stock_control',)->default(0);
            $table->integer('quantity_in_stock')->default(0);
            $table->string('plugin')->nullable()->default('NULL');
            $table->text('plugin_config');
            $table->text('upgrades');
            $table->string('type')->nullable()->default('NULL')->index();
            $table->bigInteger('priority')->nullable();
            $table->text('config');
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
        Schema::dropIfExists('products');
    }
};
