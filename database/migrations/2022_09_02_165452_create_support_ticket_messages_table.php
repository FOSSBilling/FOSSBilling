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
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('support_ticket_id')->nullable()->index();
            $table->bigInteger('client_id')->nullable()->index();
            $table->bigInteger('admin_id')->nullable()->index();
            $table->text('content');
            $table->string('attachment')->nullable()->default('NULL');
            $table->ipAddress("ip")->nullable()->default('NULL');
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
        Schema::dropIfExists('support_ticket_messages');
    }
};
