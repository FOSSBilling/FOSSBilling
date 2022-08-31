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
        Schema::create('forum_topic_messages', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("forum_topic_id")->index();
            $table->bigInteger("client_id")->index()->nullable();
            $table->bigInteger("admin_id")->index()->nullable();
            $table->text("message");
            $table->ipAddress('ip');
            $table->integer("points");
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
        Schema::dropIfExists('forum_topic_messages');
    }
};
