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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('admin_id')->nullable();
            $table->string('title')->nullable()->default('NULL');
            $table->text('description')->nullable();
            $table->text('content');
            $table->string('slug')->nullable()->default('NULL')->unique();
            $table->string('status',30)->default('draft');
            $table->string('image')->nullable()->default('NULL');
            $table->string('section')->nullable()->default('NULL');
            $table->datetime('publish_at')->nullable();
            $table->datetime('published_at')->nullable();
            $table->datetime('expires_at')->nullable();
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
        Schema::dropIfExists('posts');
    }
};
