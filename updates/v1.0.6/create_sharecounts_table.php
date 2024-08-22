<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Winter\Storm\Support\Facades\Schema;
use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use System\Classes\PluginManager;

class CreateShareCountsTable extends Migration
{
    public function up()
    {
        // Check if Winter.Blog plugin is available
        if (!PluginManager::instance()->hasPlugin('Winter.Blog')) {
            return;
        }

        Schema::create('spanjaan_blogportal_sharecounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();  // Keep the same data type but no foreign key constraint
            $table->integer('facebook')->default(0);
            $table->integer('twitter')->default(0);
            $table->integer('linkedin')->default(0);
            $table->integer('whatsapp')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('spanjaan_blogportal_sharecounts');
    }
}
