<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wedding_guests')) {
            return;
        }

        if (! Schema::hasColumn('wedding_guests', 'guest_code')) {
            Schema::table('wedding_guests', function (Blueprint $table) {
                $table->string('guest_code')->nullable()->after('guest_token');
                $table->index(['user_id', 'guest_code']);
            });
        }

        DB::table('wedding_guests')
            ->select(['id', 'user_id', 'guest_name', 'guest_code'])
            ->whereNull('guest_code')
            ->orderBy('id')
            ->chunkById(100, function ($guests): void {
                foreach ($guests as $guest) {
                    $baseCode = Str::slug((string) $guest->guest_name, '-');
                    $baseCode = $baseCode !== '' ? $baseCode : 'tamu-'.$guest->id;
                    $guestCode = $baseCode;
                    $suffix = 2;

                    while (DB::table('wedding_guests')
                        ->where('user_id', $guest->user_id)
                        ->where('guest_code', $guestCode)
                        ->where('id', '!=', $guest->id)
                        ->exists()
                    ) {
                        $guestCode = $baseCode.'-'.$suffix++;
                    }

                    DB::table('wedding_guests')
                        ->where('id', $guest->id)
                        ->update([
                            'guest_code' => $guestCode,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wedding_guests') || ! Schema::hasColumn('wedding_guests', 'guest_code')) {
            return;
        }

        Schema::table('wedding_guests', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'guest_code']);
            $table->dropColumn('guest_code');
        });
    }
};
