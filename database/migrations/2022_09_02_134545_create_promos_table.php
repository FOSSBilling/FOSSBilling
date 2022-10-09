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
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->string('code',100)->nullable()->default('NULL');
            $table->text('description');
            $table->string('type',30)->default('percentage');
            $table->decimal('value',18,2)->nullable();
            $table->integer('maxuses',)->default(0);
            $table->integer('used',)->default('0');
            $table->tinyInteger('freesetup',)->default(0);
            $table->tinyInteger('once_per_client',)->default(0);
            $table->tinyInteger('recurring',)->default(0);
            $table->tinyInteger('active',)->default(0);
            $table->text('products');
            $table->text('periods');
            $table->text('client_groups');
            $table->datetime('start_at')->nullable();
            $table->datetime('end_at')->nullable();
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
        Schema::dropIfExists('promos');
    }
};
