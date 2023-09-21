<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSMultisitesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('s_multisites', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('active')->default(0)->index();
            $table->tinyInteger('hide_from_tree')->default(0)->index();
            $table->integer('resource')->unique();
            $table->string('key')->unique();
            $table->string('domain', 255)->index();
            $table->string('site_name', 255);
            $table->integer('site_start')->default(0);
            $table->integer('error_page')->default(0);
            $table->integer('unauthorized_page')->default(0);
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
        Schema::dropIfExists('s_multisites');
    }
}
