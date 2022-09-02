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
        Schema::create('support_helpdesks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->default('NULL');
            $table->string('email')->nullable()->default('NULL');
            $table->smallInteger('close_after',)->default('24');
            $table->tinyInteger('can_reopen',)->default('0');
            $table->text('signature');
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
        Schema::dropIfExists('support_helpdesks');
    }
};
