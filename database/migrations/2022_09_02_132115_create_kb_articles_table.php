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
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('kb_article_category_id',)->nullable();
            $table->integer('views')->default(0);
            $table->string('title',100)->nullable()->default('NULL');
            $table->text('content');
            $table->string('slug')->nullable()->default('NULL')->unique();
            $table->string('status',30)->default('active');
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
        Schema::dropIfExists('kb_articles');
    }
};
