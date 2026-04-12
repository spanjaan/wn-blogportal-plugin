<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use System\Classes\PluginManager;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class CreateShareCountsTable extends Migration
{
    public function up(): void
    {
        if (!PluginManager::instance()->hasPlugin('Winter.Blog')) {
            return;
        }

        Schema::create('spanjaan_blogportal_sharecounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->integer('facebook')->default(0);
            $table->integer('twitter')->default(0);
            $table->integer('linkedin')->default(0);
            $table->integer('whatsapp')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spanjaan_blogportal_sharecounts');
    }
}
