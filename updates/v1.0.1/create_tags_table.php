<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use System\Classes\PluginManager;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class CreateTagsTable extends Migration
{
    public function up(): void
    {
        if (!PluginManager::instance()->hasPlugin('Winter.Blog')) {
            return;
        }

        Schema::create('spanjaan_blogportal_tags', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 64)->unique();
            $table->string('title', 128)->nullable();
            $table->text('description')->nullable();
            $table->boolean('promote')->default(false)->index();
            $table->string('color', 32)->default('primary');
            $table->timestamps();
            $table->index('slug', 'idx_tags_slug');
        });

        Schema::create('spanjaan_blogportal_tags_posts', function (Blueprint $table) {
            $table->integer('tag_id')->unsigned();
            $table->integer('post_id')->unsigned();
            $table->primary(['tag_id', 'post_id']);
            $table->foreign('tag_id')->references('id')->on('spanjaan_blogportal_tags')->onDelete('cascade');
            $table->foreign('post_id')->references('id')->on('winter_blog_posts')->onDelete('cascade');
            $table->index('post_id', 'idx_tags_posts_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spanjaan_blogportal_tags_posts');
        Schema::dropIfExists('spanjaan_blogportal_tags');
    }
}
