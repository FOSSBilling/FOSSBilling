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
        Schema::create('support_p_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('hash')->nullable()->default('NULL');
            $table->string('author_name')->nullable()->default('NULL');
            $table->string('author_email')->nullable()->default('NULL');
            $table->string('subject')->nullable()->default('NULL');
            $table->string('status',30)->default('open');
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
        Schema::dropIfExists('support_p_tickets');
    }
};
