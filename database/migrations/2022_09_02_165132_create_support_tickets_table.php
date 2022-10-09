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
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('support_helpdesk_id')->nullable()->index();
            $table->bigInteger('client_id')->nullable()->index();
            $table->integer('priority')->default('100');
            $table->string('subject')->nullable()->default('NULL');
            $table->string('status',30)->default('open');
            $table->string('rel_type',100)->nullable()->default('NULL');
            $table->bigInteger('rel_id')->nullable();
            $table->string('rel_task',100)->nullable()->default('NULL');
            $table->text('rel_new_value');
            $table->string('rel_status',100)->nullable()->default('NULL');

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
        Schema::dropIfExists('support_tickets');
    }
};
