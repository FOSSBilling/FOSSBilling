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
        Schema::create('activity_client_emails', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->index();
            $table->string("sender");
            $table->text("recipients");
            $table->string("subject");
            $table->text("content_html");
            $table->text("content_text");
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
        Schema::dropIfExists('activity_client_emails');
    }
};
