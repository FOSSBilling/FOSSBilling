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
        Schema::create('mod_massmailers', function (Blueprint $table) {
            $table->id();
            $table->string('from_email')->nullable()->default('NULL');
            $table->string('from_name')->nullable()->default('NULL');
            $table->string('subject')->nullable()->default('NULL');
            $table->text('content');
            $table->text('filter');
            $table->string('status')->nullable()->default('NULL');
            $table->datetime('sent_at')->nullable()->useCurrent();
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
        Schema::dropIfExists('mod_massmailers');
    }
};
