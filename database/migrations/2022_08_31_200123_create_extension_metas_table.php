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
        Schema::create('extension_metas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger("client_id")->index();
            $table->string("extension");
            $table->string("rel_type");
            $table->string("rel_id");
            $table->string("meta_key");
            $table->longText("meta_value");
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
        Schema::dropIfExists('extension_metas');
    }
};
