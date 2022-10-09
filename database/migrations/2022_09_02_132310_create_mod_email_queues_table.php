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
        Schema::create('mod_email_queues', function (Blueprint $table) {
            $table->id();
            $table->string('recipient');
            $table->string('sender');
            $table->string('subject');
            $table->text('content');
            $table->string('to_name')->nullable()->default('NULL');
            $table->string('from_name')->nullable()->default('NULL');
            $table->integer('client_id',)->nullable()->index();
            $table->integer('admin_id',)->nullable()->index();
            $table->integer('priority',)->nullable();
            $table->integer('tries',);
            $table->string('status',20);
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
        Schema::dropIfExists('mod_email_queues');
    }
};
