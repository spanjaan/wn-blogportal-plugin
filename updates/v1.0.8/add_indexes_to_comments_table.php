<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Illuminate\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddIndexesToCommentsTable extends Migration
{
    public function up(): void
    {
        Schema::table('spanjaan_blogportal_comments', function (Blueprint $table) {
            $table->index('post_id', 'idx_comments_post_id');
            $table->index('status', 'idx_comments_status');
            $table->index('parent_id', 'idx_comments_parent_id');
            $table->index(['post_id', 'status'], 'idx_comments_post_status');
            $table->index('created_at', 'idx_comments_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('spanjaan_blogportal_comments', function (Blueprint $table) {
            $table->dropIndex('idx_comments_post_id');
            $table->dropIndex('idx_comments_status');
            $table->dropIndex('idx_comments_parent_id');
            $table->dropIndex('idx_comments_post_status');
            $table->dropIndex('idx_comments_created_at');
        });
    }
}
