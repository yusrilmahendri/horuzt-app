<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\MusicTrack;
use App\Models\PaketUndangan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MusicInvitationModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::connection('sqlite')->getPdo();

        $this->createMinimalSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('public/music/test-catalog');

        parent::tearDown();
    }

    public function test_music_options_and_selection_return_frontend_contract(): void
    {
        $user = $this->userWithPackage('ruby');
        $default = $this->track('Default Song', true, 1);
        $selected = $this->track('Selected Song', false, 2);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/music-options')
            ->assertOk()
            ->assertJsonPath('catalog.0.id', $default->id)
            ->assertJsonPath('selected_music_id', null)
            ->assertJsonPath('default_music.id', $default->id)
            ->assertJsonPath('custom_music', null)
            ->assertJsonPath('resolved_music_url', $default->url)
            ->assertJsonPath('can_upload_custom_music', false)
            ->assertJsonPath('music_source_type', 'default');

        $this->putJson('/api/v1/user/music-selection', ['music_id' => $selected->id])
            ->assertOk()
            ->assertJsonPath('selected_music_id', $selected->id)
            ->assertJsonPath('selected_music.id', $selected->id)
            ->assertJsonPath('music_source_type', 'admin_catalog')
            ->assertJsonPath('resolved_music_url', $selected->url);
    }

    public function test_diamond_and_platinum_users_can_upload_custom_music(): void
    {
        $this->track('Default Song', true);

        foreach (['diamond', 'platinum'] as $code) {
            $user = $this->userWithPackage($code);
            Sanctum::actingAs($user);

            $this->postJson('/api/v1/user/custom-music', [
                'musik' => UploadedFile::fake()->create("{$code}.mp3", 64, 'audio/mpeg'),
            ])
                ->assertOk()
                ->assertJsonPath('message', 'Musik pribadi berhasil diunggah.')
                ->assertJsonPath('can_upload_custom_music', true)
                ->assertJsonPath('music_source_type', 'user_upload')
                ->assertJsonPath('custom_music.url', fn ($url) => is_string($url) && str_contains($url, '/storage/music/'));
        }
    }

    public function test_diamond_user_can_upload_music_up_to_twenty_mb(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('twenty-mb.mp3', 20480, 'audio/mpeg'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Musik pribadi berhasil diunggah.')
            ->assertJsonPath('music_source_type', 'user_upload');
    }

    public function test_custom_music_upload_accepts_allowed_extension_even_with_non_audio_mime(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('downloaded.mp3', 256, 'application/octet-stream'),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Musik pribadi berhasil diunggah.')
            ->assertJsonPath('music_source_type', 'user_upload');
    }

    public function test_custom_music_upload_accepts_wav_ogg_m4a_and_aac_extensions(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        foreach (['wav', 'ogg', 'm4a', 'aac'] as $extension) {
            $this->postJson('/api/v1/user/custom-music', [
                'musik' => UploadedFile::fake()->create("track.{$extension}", 128, 'application/octet-stream'),
            ])
                ->assertOk()
                ->assertJsonPath('message', 'Musik pribadi berhasil diunggah.')
                ->assertJsonPath('music_source_type', 'user_upload');
        }
    }

    public function test_user_can_select_global_catalog_track_via_backend_catalog_provider(): void
    {
        $user = $this->userWithPackage('ruby');
        $global = $this->externalTrack('Global Song');
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/user/music-selection', [
            'source_type' => 'global_catalog',
            'global_music_id' => $global->id,
        ])
            ->assertOk()
            ->assertJsonPath('music_source_type', 'global_catalog')
            ->assertJsonPath('selected_global_music_id', $global->id)
            ->assertJsonPath('resolved_music_url', $global->stream_url);
    }

    public function test_default_mode_uses_first_active_catalog_track_when_no_explicit_default_exists(): void
    {
        $user = $this->userWithPackage('ruby');
        $fallback = $this->track('Fallback Active Song', false, 1);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/music-selection')
            ->assertOk()
            ->assertJsonPath('music_source_type', 'default')
            ->assertJsonPath('default_music.id', $fallback->id)
            ->assertJsonPath('selected_music.id', $fallback->id)
            ->assertJsonPath('resolved_music_url', $fallback->url)
            ->assertJsonPath('music_info.has_music', true)
            ->assertJsonPath('music_info.music_source_type', 'default')
            ->assertJsonPath('music_resolution_status', 'resolved');
    }

    public function test_default_mode_returns_clear_status_when_catalog_has_no_active_track(): void
    {
        $user = $this->userWithPackage('ruby');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/music-selection')
            ->assertOk()
            ->assertJsonPath('music_source_type', 'default')
            ->assertJsonPath('default_music', null)
            ->assertJsonPath('selected_music', null)
            ->assertJsonPath('resolved_music_url', null)
            ->assertJsonPath('music_info', null)
            ->assertJsonPath('music_resolution_status', 'no_default_track')
            ->assertJsonPath('music_resolution_message', 'Belum ada musik default aktif di katalog.');
    }

    public function test_non_diamond_or_platinum_user_cannot_upload_custom_music(): void
    {
        $user = $this->userWithPackage('ruby');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('song.mp3', 64, 'audio/mpeg'),
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Upload musik pribadi hanya tersedia untuk paket Diamond/Platinum.')
            ->assertJsonPath('errors.musik.0', 'Upload musik pribadi hanya tersedia untuk paket Diamond/Platinum.');
    }

    public function test_custom_music_upload_validation_messages_are_indonesian(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('song.txt', 64, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.')
            ->assertJsonPath('errors.musik.0', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.');

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('shell.php', 64, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.')
            ->assertJsonPath('errors.musik.0', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.');

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('big-song.mp3', 21000, 'audio/mpeg'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ukuran file maksimal 20 MB.')
            ->assertJsonPath('errors.musik.0', 'Ukuran file maksimal 20 MB.');
    }

    public function test_upload_replaces_single_active_custom_music_and_delete_falls_back(): void
    {
        $user = $this->userWithPackage('diamond');
        $default = $this->track('Default Song', true);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('first.mp3', 64, 'audio/mpeg'),
        ])->assertOk();
        $firstPath = Setting::where('user_id', $user->id)->firstOrFail()->musik;

        $this->postJson('/api/v1/user/custom-music', [
            'musik' => UploadedFile::fake()->create('second.mp3', 64, 'audio/mpeg'),
        ])
            ->assertOk()
            ->assertJsonPath('music_source_type', 'user_upload');

        $setting = Setting::where('user_id', $user->id)->firstOrFail();
        $this->assertNotSame($firstPath, $setting->musik);

        $this->deleteJson('/api/v1/user/custom-music')
            ->assertOk()
            ->assertJsonPath('setting.musik', null)
            ->assertJsonPath('music_source_type', 'default')
            ->assertJsonPath('resolved_music_url', $default->url);
    }

    public function test_admin_can_upload_catalog_music_with_allowed_extension_even_if_mime_is_generic(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/music-tracks', [
            'title' => 'Admin Uploaded Song',
            'musik' => UploadedFile::fake()->create('catalog-download.mp3', 256, 'application/octet-stream'),
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik katalog berhasil diupload.');
    }

    public function test_admin_can_upload_catalog_music_through_legacy_music_upload_endpoint(): void
    {
        Storage::fake('public');
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $this->postJson('/api/music/upload', [
            'title' => 'Legacy Catalog Song',
            'musik' => UploadedFile::fake()->create('legacy-song.mp3', 256, 'application/octet-stream'),
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik katalog berhasil diupload.')
            ->assertJsonPath('data.title', 'Legacy Catalog Song');

        $this->getJson('/api/music/tracks')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Legacy Catalog Song');
    }

    public function test_regular_user_cannot_upload_catalog_music_through_legacy_endpoint(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        $this->postJson('/api/music/upload', [
            'title' => 'Blocked Catalog Song',
            'musik' => UploadedFile::fake()->create('blocked.mp3', 256, 'application/octet-stream'),
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak memiliki akses untuk mengupload katalog musik.');
    }

    public function test_admin_catalog_upload_still_rejects_invalid_extension_and_oversized_file(): void
    {
        $admin = $this->adminUser();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/music-tracks', [
            'title' => 'Invalid Extension Song',
            'musik' => UploadedFile::fake()->create('catalog.txt', 256, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.');

        $this->postJson('/api/v1/admin/music-tracks', [
            'title' => 'Too Big Song',
            'musik' => UploadedFile::fake()->create('catalog.mp3', 21000, 'application/octet-stream'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ukuran file maksimal 20 MB.');

        $this->postJson('/api/music/upload', [
            'title' => 'Legacy Invalid Extension Song',
            'musik' => UploadedFile::fake()->create('catalog.txt', 256, 'text/plain'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Format file tidak didukung. Gunakan MP3, WAV, M4A, AAC, atau OGG.');

        $this->postJson('/api/music/upload', [
            'title' => 'Legacy Too Big Song',
            'musik' => UploadedFile::fake()->create('catalog.mp3', 21000, 'application/octet-stream'),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ukuran file maksimal 20 MB.');
    }

    private function userWithPackage(string $code): User
    {
        $user = User::create([
            'name' => 'Music User',
            'email' => 'music-user-' . str()->random(8) . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ])->save();
        $user->assignRole('user');

        $package = PaketUndangan::create([
            'code' => $code,
            'jenis_paket' => 'Paket ' . ucfirst($code),
            'name_paket' => 'Paket ' . ucfirst($code),
            'price' => 100000,
            'masa_aktif' => 30,
        ]);

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step3',
            'payment_status' => 'paid',
            'package_features_snapshot' => [
                'code' => $code,
                'name_paket' => $package->getRawOriginal('name_paket'),
            ],
        ]);

        return $user;
    }

    private function adminUser(): User
    {
        $user = User::create([
            'name' => 'Admin Music',
            'email' => 'admin-music-' . str()->random(8) . '@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ])->save();
        $user->assignRole('admin');

        return $user;
    }

    private function track(string $title, bool $isDefault = false, int $sortOrder = 0): MusicTrack
    {
        $slug = str($title)->slug() . '-' . str()->random(6);
        $path = "public/music/test-catalog/{$slug}.mp3";
        Storage::put($path, 'ID3 test audio');

        return MusicTrack::create([
            'title' => $title,
            'artist' => 'Sena',
            'slug' => $slug,
            'file_path' => $path,
            'mime_type' => 'audio/mpeg',
            'file_size' => 14,
            'is_active' => true,
            'is_default' => $isDefault,
            'sort_order' => $sortOrder,
            'source' => 'sena_digital',
        ]);
    }

    private function externalTrack(string $title, int $sortOrder = 0)
    {
        return \App\Models\ExternalMusicTrack::create([
            'title' => $title,
            'artist' => 'Global Artist',
            'provider' => 'global',
            'provider_track_id' => str()->random(12),
            'stream_url' => 'https://global.example.test/' . str($title)->slug() . '.mp3',
            'mime_type' => 'audio/mpeg',
            'is_active' => true,
            'sort_order' => $sortOrder,
            'payload' => ['title' => $title],
            'last_synced_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('whatsapp_verified_at')->nullable();
            $table->string('verification_channel', 20)->nullable();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('paket_undangans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->nullable();
            $table->string('jenis_paket');
            $table->string('name_paket');
            $table->decimal('price', 10, 2);
            $table->integer('masa_aktif');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('paket_undangan_id');
            $table->string('status')->default('step1');
            $table->string('payment_status')->default('pending');
            $table->json('package_features_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('domain')->nullable();
            $table->string('token')->nullable();
            $table->string('musik')->nullable();
            $table->foreignId('music_track_id')->nullable();
            $table->string('music_source_type')->nullable();
            $table->foreignId('external_music_track_id')->nullable();
            $table->string('salam_pembuka')->nullable();
            $table->string('salam_atas')->nullable();
            $table->string('salam_bawah')->nullable();
            $table->integer('trial_masa_aktif')->nullable();
            $table->timestamps();
        });

        Schema::create('external_music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('provider')->default('global');
            $table->string('provider_track_id');
            $table->text('stream_url');
            $table->text('preview_url')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('music_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('artist')->nullable();
            $table->string('slug')->nullable();
            $table->string('file_path');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('source')->default('sena_digital');
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('uploaded_by')->nullable();
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
}
