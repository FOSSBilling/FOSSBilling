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
        Schema::create('queue_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('queue_id',)->nullable();
            $table->char('handle',32)->nullable()->default('NULL')->index();
            $table->string('handler')->nullable()->default('NULL');
            $table->binary("body");
            $table->char('hash',32)->nullable()->default('NULL');
            $table->double("timeout",18,2)->nullable();
            $table->text('log');
            $table->datetime('execute_at')->nullable();
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
        Schema::dropIfExists('queue_messages');
    }
};
