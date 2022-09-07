<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("form_id")->index();
            $table->string("name");
            $table->string("label");
            $table->boolean("hide_label");
            $table->string("description");
            $table->string("type");
            $table->string("default_value");
            $table->boolean("required");
            $table->boolean("hidden");
            $table->boolean("is_unique");
            $table->string("prefix");
            $table->string("suffix");
            $table->text("options");
            $table->string("show_initial")->nullable();
            $table->string("show_middle")->nullable();
            $table->string("show_prefix")->nullable();
            $table->string("show_suffix")->nullable();
            $table->integer("text_size")->nullable();
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
        Schema::dropIfExists('form_fields');
    }
};
