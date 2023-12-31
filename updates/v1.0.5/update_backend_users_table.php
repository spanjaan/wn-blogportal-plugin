<?php

declare(strict_types=1);

namespace SpAnjaan\BlogPortal\Updates;

use Winter\Storm\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use System\Classes\PluginManager;

/**
 * UpdateBackendUsers Migration
 */
class UpdateBackendUsersTable extends Migration
{
    /**
     * @inheritDoc
     */
    public function up()
    {
        Schema::table('backend_users', function (Blueprint $table) {
            $table->string('spanjaan_blogportal_display_name', 128)->nullable();
            $table->string('spanjaan_blogportal_author_slug', 128)->unique()->nullable();
            $table->text('spanjaan_blogportal_about_me')->nullable();
        });
    }

    /**
     * @inheritDoc
     */
    public function down()
    {
        if (method_exists(Schema::class, 'dropColumns')) {
            Schema::dropColumns('backend_users', ['spanjaan_blogportal_display_name', 'spanjaan_blogportal_author_slug']);
        } else {
            Schema::table('backend_users', function (Blueprint $table) {
                if (Schema::hasColumn('backend_users', 'spanjaan_blogportal_display_name')) {
                    $table->dropColumn('spanjaan_blogportal_display_name');
                }
            });
            Schema::table('backend_users', function (Blueprint $table) {
                if (Schema::hasColumn('backend_users', 'spanjaan_blogportal_author_slug')) {
                    $table->dropColumn('spanjaan_blogportal_author_slug');
                }
            });
            Schema::table('backend_users', function (Blueprint $table) {
                if (Schema::hasColumn('backend_users', 'spanjaan_blogportal_about_me')) {
                    $table->dropColumn('spanjaan_blogportal_about_me');
                }
            });
        }
    }
}
