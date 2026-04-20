<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class FixForeignKeyConstraints extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
        Schema::table('spanjaan_blogportal_tags_posts', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropIndex('idx_tags_posts_post_id');
        });

        Schema::table('spanjaan_blogportal_comments', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropForeign(['parent_id']);
            $table->dropIndex('idx_comments_post_id');
            $table->dropIndex('idx_comments_status');
            $table->dropIndex('idx_comments_parent_id');
            $table->dropIndex('idx_comments_post_status');
            $table->dropIndex('idx_comments_created_at');
        });

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
    }
}
