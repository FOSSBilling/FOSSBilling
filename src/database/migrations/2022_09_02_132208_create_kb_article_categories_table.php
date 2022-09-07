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
        Schema::create('kb_article_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title',100)->nullable()->default('NULL');
            $table->text('description');
            $table->string('slug')->nullable()->default('NULL')->unique();
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
        Schema::dropIfExists('kb_article_categories');
    }
};
