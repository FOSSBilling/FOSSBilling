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
        Schema::create('support_p_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('support_p_ticket_id')->nullable()->index();
            $table->bigInteger('admin_id')->nullable()->index();
            $table->text('content')->fulltext();
            $table->string('ip',45)->nullable()->default('NULL');
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
        Schema::dropIfExists('support_p_ticket_messages');
    }
};
