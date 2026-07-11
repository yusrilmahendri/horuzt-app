<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('acaras', function (Blueprint $table) {
            if (! Schema::hasColumn('acaras', 'address')) {
                $table->text('address')->nullable()->after('alamat');
            }
            if (! Schema::hasColumn('acaras', 'location_name')) {
                $table->string('location_name')->nullable()->after('address');
            }
            if (! Schema::hasColumn('acaras', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location_name');
            }
            if (! Schema::hasColumn('acaras', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (! Schema::hasColumn('acaras', 'google_maps_url')) {
                $table->text('google_maps_url')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('acaras', 'place_id')) {
                $table->string('place_id')->nullable()->after('google_maps_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acaras', function (Blueprint $table) {
            $columns = ['place_id', 'google_maps_url', 'longitude', 'latitude', 'location_name', 'address'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('acaras', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
