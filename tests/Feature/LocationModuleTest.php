<?php

namespace Tests\Feature;

use App\Models\Acara;
use App\Models\CountdownAcara;
use App\Models\Invitation;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::connection('sqlite')->getPdo();

        $this->createMinimalSchema();
    }

    public function test_user_can_store_manual_address_without_coordinates(): void
    {
        $user = $this->userWithCountdownAndPublicDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/submission-acara', [
            'jenis_acara' => 'akad',
            'nama_acara' => 'Akad Nikah',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'address' => 'Gedung Serbaguna Jakarta',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Data lokasi acara berhasil disimpan.')
            ->assertJsonPath('data.alamat', 'Gedung Serbaguna Jakarta')
            ->assertJsonPath('data.address', 'Gedung Serbaguna Jakarta')
            ->assertJsonPath('data.latitude', null)
            ->assertJsonPath('data.longitude', null)
            ->assertJsonPath('data.google_maps_url', null);
    }

    public function test_user_can_store_map_point_and_backend_generates_google_maps_url(): void
    {
        $user = $this->userWithCountdownAndPublicDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/submission-acara', [
            'jenis_acara' => 'resepsi',
            'nama_acara' => 'Resepsi',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '11:00',
            'end_acara' => '13:00',
            'location_name' => 'Balai Kartini',
            'latitude' => -6.2293867,
            'longitude' => 106.8292001,
            'place_id' => 'place-123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.location_name', 'Balai Kartini')
            ->assertJsonPath('data.latitude', -6.2293867)
            ->assertJsonPath('data.longitude', 106.8292001)
            ->assertJsonPath('data.google_maps_url', 'https://www.google.com/maps?q=-6.2293867,106.8292001')
            ->assertJsonPath('data.link_maps', 'https://www.google.com/maps?q=-6.2293867,106.8292001')
            ->assertJsonPath('data.place_id', 'place-123');
    }

    public function test_latitude_and_longitude_must_be_paired(): void
    {
        $user = $this->userWithCountdownAndPublicDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/submission-acara', [
            'jenis_acara' => 'akad',
            'nama_acara' => 'Akad Nikah',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'latitude' => -6.2293867,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Latitude dan longitude harus diisi berpasangan.');
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $user = $this->userWithCountdownAndPublicDomain();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/submission-acara', [
            'jenis_acara' => 'akad',
            'nama_acara' => 'Akad Nikah',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'latitude' => -91,
            'longitude' => 106.8292001,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_legacy_alamat_and_link_maps_still_appear_in_user_response(): void
    {
        $user = $this->userWithCountdownAndPublicDomain();
        Sanctum::actingAs($user);

        Acara::create([
            'user_id' => $user->id,
            'countdown_id' => CountdownAcara::where('user_id', $user->id)->value('id'),
            'jenis_acara' => 'akad',
            'nama_acara' => 'Akad Nikah',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'alamat' => 'Alamat lama',
            'link_maps' => 'https://maps.example.test/legacy',
        ]);

        $this->getJson('/api/v1/user/acara')
            ->assertOk()
            ->assertJsonPath('data.events.akad.alamat', 'Alamat lama')
            ->assertJsonPath('data.events.akad.address', 'Alamat lama')
            ->assertJsonPath('data.events.akad.link_maps', 'https://maps.example.test/legacy')
            ->assertJsonPath('data.events.akad.google_maps_url', 'https://maps.example.test/legacy');
    }

    public function test_public_wedding_profile_returns_location_fields(): void
    {
        $user = $this->userWithCountdownAndPublicDomain('lokasi-public');

        Acara::create([
            'user_id' => $user->id,
            'countdown_id' => CountdownAcara::where('user_id', $user->id)->value('id'),
            'jenis_acara' => 'akad',
            'nama_acara' => 'Akad Nikah',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'alamat' => 'Alamat public',
            'link_maps' => '',
            'address' => 'Alamat public modern',
            'location_name' => 'Venue Public',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'place_id' => 'public-place',
        ]);

        $this->getJson('/api/v1/wedding-profile/public?domain=lokasi-public')
            ->assertOk()
            ->assertJsonPath('data.events.0.alamat', 'Alamat public modern')
            ->assertJsonPath('data.events.0.address', 'Alamat public modern')
            ->assertJsonPath('data.events.0.location_name', 'Venue Public')
            ->assertJsonPath('data.events.0.google_maps_url', 'https://www.google.com/maps?q=-6.2,106.8')
            ->assertJsonPath('data.events.0.link_maps', 'https://www.google.com/maps?q=-6.2,106.8')
            ->assertJsonPath('data.events.0.place_id', 'public-place');
    }

    private function userWithCountdownAndPublicDomain(string $domain = 'lokasi-test'): User
    {
        $user = User::create([
            'name' => 'Location User',
            'email' => 'location-' . str()->random(8) . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $this->assignUserRole($user);

        DB::table('countdown_acaras')->insert([
            'user_id' => $user->id,
            'name_countdown' => 'Wedding Day',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => $domain,
            'token' => str()->random(16),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invitations')->insert([
            'user_id' => $user->id,
            'status' => 'step3',
            'payment_status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function assignUserRole(User $user): void
    {
        DB::table('roles')->insertOrIgnore([
            'id' => 1,
            'name' => 'user',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => 1,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('kode_pemesanan')->nullable();
            $table->timestamps();
        });

        Schema::create('countdown_acaras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('name_countdown');
            $table->timestamps();
        });

        Schema::create('acaras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('countdown_id')->nullable();
            $table->string('jenis_acara')->nullable();
            $table->string('nama_acara');
            $table->string('tanggal_acara');
            $table->string('start_acara');
            $table->string('end_acara');
            $table->string('alamat')->default('');
            $table->text('address')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('google_maps_url')->nullable();
            $table->string('place_id')->nullable();
            $table->text('link_maps')->default('');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('domain')->nullable();
            $table->string('token')->nullable();
            $table->string('musik')->nullable();
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('paket_undangan_id')->nullable();
            $table->string('status')->default('step1');
            $table->string('payment_status')->default('pending');
            $table->string('domain')->nullable();
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->string('kode_pemesanan')->nullable();
            $table->json('package_features_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('paket_undangans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('jenis_paket')->nullable();
            $table->string('name_paket')->nullable();
            $table->timestamps();
        });

        Schema::create('category_themas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('paket_undangan_category_thema', function (Blueprint $table) {
            $table->foreignId('paket_undangan_id');
            $table->foreignId('category_thema_id');
            $table->timestamps();
        });

        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('kd_status')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('filter_undangans', fn (Blueprint $table) => $this->emptyRelatedTable($table));
        Schema::create('ceritas', fn (Blueprint $table) => $this->emptyRelatedTable($table));
        Schema::create('qoutes', fn (Blueprint $table) => $this->emptyRelatedTable($table));
        Schema::create('testimonis', fn (Blueprint $table) => $this->emptyRelatedTable($table));
        Schema::create('buku_tamus', fn (Blueprint $table) => $this->emptyRelatedTable($table));
        Schema::create('ucapans', fn (Blueprint $table) => $this->emptyRelatedTable($table));

        Schema::create('galeries', function (Blueprint $table) {
            $this->emptyRelatedTable($table);
            $table->boolean('status')->default(true);
            $table->string('photo_type')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
        });

        Schema::create('banks', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('rekenings', function (Blueprint $table) {
            $this->emptyRelatedTable($table);
            $table->foreignId('bank_id')->nullable();
        });

        Schema::create('themas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('jenis_themas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('result_themas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('thema_id')->nullable();
            $table->foreignId('jenis_id')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }

    private function emptyRelatedTable(Blueprint $table): void
    {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->timestamps();
    }
}
