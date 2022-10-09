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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string("action_code")->index();;
            $table->enum("category", ['general', 'domain', 'invoice', 'hosting', 'support', 'download', 'custom', 'license']);
            $table->boolean("enabled")->default(1);
            $table->string("subject");
            $table->text("content");
            $table->text("description");
            $table->text("vars");

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('email_templates');
    }
};
