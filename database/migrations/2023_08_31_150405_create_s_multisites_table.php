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

        // Seed default domain
        $default = [
            'active' => 1,
            'resource' => 0,
            'key' => 'default',
            'domain' => get_by_key($_SERVER, 'HTTP_HOST', 'localhost'),
            'site_name' => evo()->getConfig('site_name', 'Default'),
            'site_start' => evo()->getConfig('site_start', 1),
            'error_page' => evo()->getConfig('error_page', 1),
            'unauthorized_page' => evo()->getConfig('unauthorized_page', 1),
        ];

        $item = new \Seiger\sMultisite\Models\sMultisite();
        foreach ($default as $key => $value) {
            $item->{$key} = $value;
        }
        $item->save();
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
