<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galeries', function (Blueprint $table) {
            if (! Schema::hasColumn('galeries', 'photo_type')) {
                $table->string('photo_type', 20)->default('gallery')->after('user_id');
            }

            if (! Schema::hasColumn('galeries', 'file_path')) {
                $table->string('file_path')->nullable()->after('photo');
            }

            if (! Schema::hasColumn('galeries', 'file_url')) {
                $table->string('file_url')->nullable()->after('file_path');
            }

            if (! Schema::hasColumn('galeries', 'description')) {
                $table->text('description')->nullable()->after('file_url');
            }

            if (! Schema::hasColumn('galeries', 'position')) {
                $table->string('position', 30)->default('center')->after('description');
            }

            if (! Schema::hasColumn('galeries', 'display_mode')) {
                $table->string('display_mode', 20)->default('cover')->after('position');
            }

            if (! Schema::hasColumn('galeries', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('display_mode');
            }

            if (! Schema::hasColumn('galeries', 'original_name')) {
                $table->string('original_name')->nullable()->after('sort_order');
            }

            if (! Schema::hasColumn('galeries', 'original_size')) {
                $table->unsignedBigInteger('original_size')->nullable()->after('original_name');
            }

            if (! Schema::hasColumn('galeries', 'compressed_size')) {
                $table->unsignedBigInteger('compressed_size')->nullable()->after('original_size');
            }

            if (! Schema::hasColumn('galeries', 'mime_type')) {
                $table->string('mime_type', 100)->nullable()->after('compressed_size');
            }

            if (! Schema::hasColumn('galeries', 'quality')) {
                $table->unsignedTinyInteger('quality')->default(85)->after('mime_type');
            }
        });

        Schema::table('galeries', function (Blueprint $table) {
            $table->index('user_id', 'galeries_user_id_idx');
            $table->index('photo_type', 'galeries_photo_type_idx');
            $table->index(['user_id', 'photo_type'], 'galeries_user_photo_type_idx');
            $table->index(['user_id', 'sort_order'], 'galeries_user_sort_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('galeries', function (Blueprint $table) {
            $table->dropIndex('galeries_user_id_idx');
            $table->dropIndex('galeries_photo_type_idx');
            $table->dropIndex('galeries_user_photo_type_idx');
            $table->dropIndex('galeries_user_sort_order_idx');
        });

        Schema::table('galeries', function (Blueprint $table) {
            foreach ([
                'photo_type',
                'file_path',
                'file_url',
                'description',
                'position',
                'display_mode',
                'sort_order',
                'original_name',
                'original_size',
                'compressed_size',
                'mime_type',
                'quality',
            ] as $column) {
                if (Schema::hasColumn('galeries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
