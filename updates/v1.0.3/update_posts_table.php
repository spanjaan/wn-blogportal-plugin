<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use System\Classes\PluginManager;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class CreateViewsTable extends Migration
{
    public function up(): void
    {
        if (!PluginManager::instance()->hasPlugin('Winter.Blog')) {
            return;
        }

        Schema::table('winter_blog_posts', function (Blueprint $table) {
            $table->integer('spanjaan_blogportal_views')->unsigned()->default(0);
            $table->integer('spanjaan_blogportal_unique_views')->unsigned()->default(0);
        });
    }

    public function down(): void
    {
        if (method_exists(Schema::class, 'dropColumns')) {
            Schema::dropColumns('winter_blog_posts', [
                'spanjaan_blogportal_views',
                'spanjaan_blogportal_unique_views',
            ]);
        } else {
            Schema::table('winter_blog_posts', function (Blueprint $table) {
                if (Schema::hasColumn('winter_blog_posts', 'spanjaan_blogportal_views')) {
                    $table->dropColumn('spanjaan_blogportal_views');
                }
            });
            Schema::table('winter_blog_posts', function (Blueprint $table) {
                if (Schema::hasColumn('winter_blog_posts', 'spanjaan_blogportal_unique_views')) {
                    $table->dropColumn('spanjaan_blogportal_unique_views');
                }
            });
        }
    }
}
