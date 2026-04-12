<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddIndexesToShareCountsAndVisitorsTable extends Migration
{
    public function up(): void
    {
        Schema::table('spanjaan_blogportal_sharecounts', function (Blueprint $table) {
            $table->index('post_id', 'idx_sharecounts_post_id');
            $table->index('created_at', 'idx_sharecounts_created_at');
        });

        Schema::table('spanjaan_blogportal_visitors', function (Blueprint $table) {
            $table->index('user', 'idx_visitors_user');
        });

        Schema::table('spanjaan_blogportal_tags', function (Blueprint $table) {
            $table->index('slug', 'idx_tags_slug');
            $table->index('promote', 'idx_tags_promote');
        });

        Schema::table('spanjaan_blogportal_tags_posts', function (Blueprint $table) {
            $table->index('post_id', 'idx_tags_posts_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('spanjaan_blogportal_sharecounts', function (Blueprint $table) {
            $table->dropIndex('idx_sharecounts_post_id');
            $table->dropIndex('idx_sharecounts_created_at');
        });

        Schema::table('spanjaan_blogportal_visitors', function (Blueprint $table) {
            $table->dropIndex('idx_visitors_user');
        });

        Schema::table('spanjaan_blogportal_tags', function (Blueprint $table) {
            $table->dropIndex('idx_tags_slug');
            $table->dropIndex('idx_tags_promote');
        });

        Schema::table('spanjaan_blogportal_tags_posts', function (Blueprint $table) {
            $table->dropIndex('idx_tags_posts_post_id');
        });
    }
}
