<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galeries', function (Blueprint $table) {
            if (! Schema::hasColumn('galeries', 'focal_point_x')) {
                $table->decimal('focal_point_x', 5, 2)->nullable()->after('display_mode');
            }

            if (! Schema::hasColumn('galeries', 'focal_point_y')) {
                $table->decimal('focal_point_y', 5, 2)->nullable()->after('focal_point_x');
            }

            if (! Schema::hasColumn('galeries', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('focal_point_y');
            }

            if (Schema::hasColumn('galeries', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->change();
            }
        });

        Schema::table('galeries', function (Blueprint $table) {
            $table->index(['user_id', 'photo_type', 'is_featured'], 'galeries_user_type_featured_idx');
            $table->index(['user_id', 'photo_type', 'sort_order'], 'galeries_user_type_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::table('galeries', function (Blueprint $table) {
            $table->dropIndex('galeries_user_type_featured_idx');
            $table->dropIndex('galeries_user_type_sort_idx');
        });

        Schema::table('galeries', function (Blueprint $table) {
            foreach (['focal_point_x', 'focal_point_y', 'is_featured'] as $column) {
                if (Schema::hasColumn('galeries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
