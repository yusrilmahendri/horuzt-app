<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\PaketUndangan;
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

class GalleryVideoUploadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createMinimalSchema();
    }

    public function test_post_hanya_url_video_tanpa_image_berhasil(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $this->postJson('/api/v1/user/photos', [
            'photo_type' => 'gallery',
            'url_video' => 'https://www.youtube.com/watch?v=abc123',
            'description' => 'Video akad',
        ])
            ->assertCreated()
            ->assertJsonPath('data.photo', null)
            ->assertJsonPath('data.photo_url', null)
            ->assertJsonPath('data.image_url', null)
            ->assertJsonPath('data.preview_url', null)
            ->assertJsonPath('data.url_video', 'https://www.youtube.com/watch?v=abc123')
            ->assertJsonPath('data.video_url', 'https://www.youtube.com/watch?v=abc123')
            ->assertJsonPath('data.description', 'Video akad');
    }

    public function test_post_url_video_dengan_image_cover_berhasil(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $this->post('/api/v1/user/photos', [
            'photo_type' => 'gallery',
            'video_url' => 'https://youtu.be/abc123',
            'image' => UploadedFile::fake()->image('cover.jpg', 800, 600),
        ])
            ->assertCreated()
            ->assertJsonPath('data.url_video', 'https://youtu.be/abc123')
            ->assertJsonPath('data.video_url', 'https://youtu.be/abc123')
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/photos/'));
    }

    public function test_post_foto_gallery_tanpa_url_video_tetap_wajib_image(): void
    {
        $this->actingUser();

        $this->postJson('/api/v1/user/photos', [
            'photo_type' => 'gallery',
            'description' => 'Tanpa foto',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_get_gallery_mengembalikan_url_video_dan_photo_url(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $this->post('/api/v1/user/photos', [
            'photo_type' => 'gallery',
            'link_video' => 'https://www.youtube.com/embed/abc123',
            'image' => UploadedFile::fake()->image('cover.png', 800, 600),
        ])->assertCreated();

        $this->getJson('/api/v1/user/photos?type=gallery')
            ->assertOk()
            ->assertJsonPath('data.0.url_video', 'https://www.youtube.com/embed/abc123')
            ->assertJsonPath('data.0.video_url', 'https://www.youtube.com/embed/abc123')
            ->assertJsonPath('data.0.photo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/photos/'));
    }

    public function test_upload_foto_biasa_tetap_normal(): void
    {
        Storage::fake('public');
        $this->actingUser();

        $this->post('/api/v1/user/photos', [
            'photo_type' => 'gallery',
            'image' => UploadedFile::fake()->image('gallery.jpg', 800, 600),
            'description' => 'Foto gallery',
        ])
            ->assertCreated()
            ->assertJsonPath('data.url_video', null)
            ->assertJsonPath('data.description', 'Foto gallery')
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/photos/'));
    }

    private function actingUser(): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Gallery User',
            'email' => 'gallery-video-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ])->save();
        $user->assignRole('user');

        $package = PaketUndangan::create([
            'code' => 'ruby',
            'jenis_paket' => 'Paket Ruby',
            'name_paket' => 'Paket Ruby',
            'price' => 100000,
            'masa_aktif' => 30,
        ]);

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'domain_expires_at' => now()->addDays(30),
            'payment_confirmed_at' => now(),
            'package_features_snapshot' => [
                'code' => 'ruby',
                'name_paket' => 'Paket Ruby',
            ],
        ]);

        Sanctum::actingAs($user);

        return $user;
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

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
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
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_undangan_id');
            $table->string('status')->default('step1');
            $table->string('payment_status')->default('pending');
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->json('package_features_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('galeries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('photo')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_url')->nullable();
            $table->string('photo_type', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('position', 30)->nullable();
            $table->string('display_mode', 20)->default('cover');
            $table->decimal('focal_point_x', 5, 2)->nullable();
            $table->decimal('focal_point_y', 5, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('sort_order')->default(0);
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('original_size')->nullable();
            $table->unsignedBigInteger('compressed_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedTinyInteger('quality')->nullable();
            $table->string('url_video')->nullable();
            $table->string('nama_foto')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }
}
