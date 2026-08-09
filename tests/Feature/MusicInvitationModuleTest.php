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

    public function test_music_catalog_defaults_to_first_page_with_ten_items(): void
    {
        $tracks = $this->tracks(15);

        $payload = $this->getJson('/api/music/tracks')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonCount(10, 'catalog')
            ->assertJsonCount(10, 'catalog_sections.admin_catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10)
            ->json();

        $this->assertSame($tracks[0]->id, $payload['data'][0]['id']);
        $this->assertSame($tracks[9]->id, $payload['data'][9]['id']);
    }

    public function test_music_options_defaults_to_first_page_with_five_items(): void
    {
        $user = $this->userWithPackage('ruby');
        $tracks = $this->tracks(14);
        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/user/music-options')
            ->assertOk()
            ->assertJsonCount(5, 'catalog')
            ->assertJsonCount(5, 'catalog_sections.admin_catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 5)
            ->json();

        $this->assertSame($tracks[0]->id, $payload['catalog'][0]['id']);
        $this->assertSame($tracks[4]->id, $payload['catalog'][4]['id']);
    }

    public function test_music_options_page_one_with_five_items(): void
    {
        $user = $this->userWithPackage('ruby');
        $tracks = $this->tracks(14);
        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/user/music-options?page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 5)
            ->json();

        $this->assertSame($tracks[0]->id, $payload['catalog'][0]['id']);
        $this->assertSame($tracks[4]->id, $payload['catalog'][4]['id']);
    }

    public function test_music_options_page_two_with_five_items(): void
    {
        $user = $this->userWithPackage('ruby');
        $tracks = $this->tracks(14);
        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/user/music-options?page=2&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'catalog')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 6)
            ->assertJsonPath('meta.to', 10)
            ->json();

        $this->assertSame($tracks[5]->id, $payload['catalog'][0]['id']);
        $this->assertSame($tracks[9]->id, $payload['catalog'][4]['id']);
    }

    public function test_music_options_page_three_with_five_items(): void
    {
        $user = $this->userWithPackage('ruby');
        $tracks = $this->tracks(14);
        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/user/music-options?page=3&per_page=5')
            ->assertOk()
            ->assertJsonCount(4, 'catalog')
            ->assertJsonPath('meta.current_page', 3)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 11)
            ->assertJsonPath('meta.to', 14)
            ->json();

        $this->assertSame($tracks[10]->id, $payload['catalog'][0]['id']);
        $this->assertSame($tracks[13]->id, $payload['catalog'][3]['id']);
    }

    public function test_music_options_allows_ten_and_twenty_items_per_page(): void
    {
        $user = $this->userWithPackage('ruby');
        $this->tracks(14);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/music-options?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 10);

        $this->getJson('/api/v1/user/music-options?per_page=20')
            ->assertOk()
            ->assertJsonCount(14, 'catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 14);
    }

    public function test_music_options_sanitizes_unsupported_per_page_to_five(): void
    {
        $user = $this->userWithPackage('ruby');
        $this->tracks(14);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/music-options?per_page=999')
            ->assertOk()
            ->assertJsonCount(5, 'catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 14)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 5);
    }

    public function test_music_catalog_page_two_returns_next_items(): void
    {
        $tracks = $this->tracks(15);

        $payload = $this->getJson('/api/music/tracks?page=2&per_page=10')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 15)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 11)
            ->assertJsonPath('meta.to', 15)
            ->json();

        $this->assertSame($tracks[10]->id, $payload['data'][0]['id']);
        $this->assertSame($tracks[14]->id, $payload['data'][4]['id']);
    }

    public function test_music_catalog_allows_twenty_items_per_page(): void
    {
        $this->tracks(25);

        $this->getJson('/api/music/tracks?per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.from', 1)
            ->assertJsonPath('meta.to', 20);
    }

    public function test_music_catalog_sanitizes_unsupported_per_page(): void
    {
        $this->tracks(60);

        $this->getJson('/api/music/tracks?per_page=999')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 60)
            ->assertJsonPath('meta.last_page', 6);
    }

    public function test_music_catalog_last_page_meta_is_correct(): void
    {
        $tracks = $this->tracks(37);

        $payload = $this->getJson('/api/music/tracks?page=4&per_page=10')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonPath('meta.current_page', 4)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 37)
            ->assertJsonPath('meta.last_page', 4)
            ->assertJsonPath('meta.from', 31)
            ->assertJsonPath('meta.to', 37)
            ->json();

        $this->assertSame($tracks[30]->id, $payload['data'][0]['id']);
        $this->assertSame($tracks[36]->id, $payload['data'][6]['id']);
    }

    public function test_music_options_pagination_does_not_change_selected_music_on_other_page(): void
    {
        $user = $this->userWithPackage('ruby');
        $tracks = $this->tracks(15);
        $selected = $tracks[12];

        Setting::create([
            'user_id' => $user->id,
            'music_track_id' => $selected->id,
            'music_source_type' => 'admin_catalog',
        ]);

        Sanctum::actingAs($user);

        $payload = $this->getJson('/api/v1/user/music-options?page=1&per_page=5')
            ->assertOk()
            ->assertJsonCount(5, 'catalog')
            ->assertJsonPath('selected_music_id', $selected->id)
            ->assertJsonPath('selected_catalog_id', $selected->id)
            ->assertJsonPath('selected_music.id', $selected->id)
            ->assertJsonPath('music_source_type', 'admin_catalog')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->json();

        $this->assertFalse(collect($payload['catalog'])->contains('id', $selected->id));
        $this->assertSame($selected->id, Setting::where('user_id', $user->id)->firstOrFail()->music_track_id);
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
            ->assertForbidden();
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

    public function test_admin_can_manage_catalog_tracks_through_legacy_admin_action_endpoints(): void
    {
        $admin = $this->adminUser();
        $first = $this->track('First Song', true, 1);
        $second = $this->track('Second Song', false, 2);
        Sanctum::actingAs($admin);

        $this->putJson("/api/music/tracks/{$second->id}", [
            'title' => 'georgie',
            'artist' => 'the drak',
            'subtitle' => 'the drak subtitle',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik katalog berhasil diperbarui.')
            ->assertJsonPath('data.title', 'georgie')
            ->assertJsonPath('data.artist', 'the drak')
            ->assertJsonPath('data.subtitle', 'the drak subtitle');

        $this->patchJson("/api/music/tracks/{$second->id}/status", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik katalog berhasil diperbarui.')
            ->assertJsonPath('data.is_active', false);

        $this->getJson('/api/music/tracks')
            ->assertOk()
            ->assertJsonMissing(['id' => $second->id]);

        $this->getJson('/api/music/tracks?admin=1')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $second->id,
                'is_active' => false,
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'artist',
                        'subtitle',
                        'description',
                        'stream_url',
                        'audio_url',
                        'is_active',
                        'is_default',
                        'sort_order',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
        $this->assertDatabaseHas('music_tracks', [
            'id' => $second->id,
            'is_active' => false,
            'is_default' => false,
        ]);

        $this->patchJson("/api/music/tracks/{$second->id}/status", [
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->patchJson("/api/music/tracks/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik berhasil dijadikan default.')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('music_tracks', [
            'id' => $first->id,
            'is_default' => false,
        ]);

        $this->deleteJson("/api/music/tracks/{$second->id}")
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Musik katalog berhasil dihapus.');

        $this->assertDatabaseMissing('music_tracks', ['id' => $second->id]);
    }

    public function test_regular_user_cannot_manage_catalog_tracks_through_admin_action_endpoints(): void
    {
        $user = $this->userWithPackage('diamond');
        $track = $this->track('Blocked Admin Action', false);
        $track->update(['is_active' => false]);
        Sanctum::actingAs($user);

        $this->getJson('/api/music/tracks?admin=1')
            ->assertOk()
            ->assertJsonMissing(['id' => $track->id]);

        $this->patchJson("/api/music/tracks/{$track->id}/status", [
            'is_active' => false,
        ])->assertForbidden();

        $this->patchJson("/api/music/tracks/{$track->id}/default")->assertForbidden();

        $this->deleteJson("/api/music/tracks/{$track->id}")->assertForbidden();
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

    /**
     * @return array<int,\App\Models\MusicTrack>
     */
    private function tracks(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index) => $this->track(sprintf('Catalog Song %02d', $index), $index === 1, $index))
            ->all();
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
            $table->text('description')->nullable();
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
