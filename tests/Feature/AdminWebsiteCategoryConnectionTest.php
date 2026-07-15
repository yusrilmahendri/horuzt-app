<?php

namespace Tests\Feature;

use App\Models\CategoryThemas;
use App\Models\Invitation;
use App\Models\JenisThemas;
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

class AdminWebsiteCategoryConnectionTest extends TestCase
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
        $this->seedThemeCatalog();
    }

    public function test_admin_website_categories_returns_five_connected_primary_themes(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->getJson('/api/admin/website-categories?per_page=10');

        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data'));

        $this->assertSame([
            'soft-ivory',
            'lavender-bloom',
            'garden-whisper',
            'champagne-rose',
            'diamond-garden',
        ], $themes->pluck('slug')->all());

        $champagne = $themes->firstWhere('slug', 'champagne-rose');
        $diamondGarden = $themes->firstWhere('slug', 'diamond-garden');

        $this->assertTrue($champagne['is_connected']);
        $this->assertSame('Champagne Rose', $champagne['name']);
        $this->assertSame('champagne-rose', $champagne['theme_slug']);
        $this->assertSame('champagne-rose', $champagne['master_theme_slug']);
        $this->assertSame('elegant', $champagne['category_slug']);
        $this->assertSame('elegant', $champagne['category_user_slug']);
        $this->assertSame('diamond', $champagne['package_required']);
        $this->assertSame('diamond', $champagne['package_code']);
        $this->assertSame('diamond', $champagne['package_required_detail']['code']);

        $this->assertTrue($diamondGarden['is_connected']);
        $this->assertSame('Diamond Garden', $diamondGarden['name']);
        $this->assertSame('diamond-garden', $diamondGarden['theme_slug']);
        $this->assertSame('diamond-garden', $diamondGarden['master_theme_slug']);
        $this->assertSame('luxury', $diamondGarden['category_slug']);
        $this->assertSame('luxury', $diamondGarden['category_user_slug']);
        $this->assertSame('diamond', $diamondGarden['package_required']);
        $this->assertSame('diamond', $diamondGarden['package_code']);
        $this->assertSame('diamond', $diamondGarden['package_required_detail']['code']);
    }

    public function test_admin_themes_response_keeps_theme_slug_and_package_separate(): void
    {
        Sanctum::actingAs($this->adminUser());

        $response = $this->getJson('/api/admin/themes?type=website&per_page=10');

        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data.data'));
        $champagne = $themes->firstWhere('slug', 'champagne-rose');
        $diamondGarden = $themes->firstWhere('slug', 'diamond-garden');

        $this->assertSame('champagne-rose', $champagne['theme_slug']);
        $this->assertSame('champagne-rose', $champagne['master_theme_slug']);
        $this->assertSame('diamond', $champagne['package_required']);
        $this->assertSame('elegant', $champagne['category_slug']);
        $this->assertTrue($champagne['is_connected']);

        $this->assertSame('diamond-garden', $diamondGarden['theme_slug']);
        $this->assertSame('diamond-garden', $diamondGarden['master_theme_slug']);
        $this->assertSame('diamond', $diamondGarden['package_required']);
        $this->assertSame('luxury', $diamondGarden['category_slug']);
        $this->assertTrue($diamondGarden['is_connected']);
    }

    public function test_admin_can_partial_update_status_without_nama_kategori(): void
    {
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'champagne-rose')->firstOrFail();

        $this->putJson("/api/admin/website-categories/{$theme->id}", [
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'champagne-rose')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_can_partial_update_is_active_without_nama_kategori(): void
    {
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'diamond-garden')->firstOrFail();

        $this->putJson("/api/admin/website-categories/{$theme->id}", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'diamond-garden')
            ->assertJsonPath('data.nama_kategori', 'Diamond Garden')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('jenis_themas', [
            'id' => $theme->id,
            'name' => 'Diamond Garden',
            'is_active' => false,
        ]);
    }

    public function test_admin_can_partial_update_urutan_without_nama_kategori(): void
    {
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'garden-whisper')->firstOrFail();

        $this->putJson("/api/admin/website-categories/{$theme->id}", [
            'urutan' => 77,
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'garden-whisper')
            ->assertJsonPath('data.nama_kategori', 'Garden Whisper')
            ->assertJsonPath('data.urutan', 77);

        $this->assertDatabaseHas('jenis_themas', [
            'id' => $theme->id,
            'name' => 'Garden Whisper',
            'sort_order' => 77,
        ]);
    }

    public function test_admin_can_partial_update_preview_image_without_nama_kategori(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'lavender-bloom')->firstOrFail();
        $categoryId = $theme->category_id;
        $themeCount = JenisThemas::count();
        $categoryCount = CategoryThemas::count();

        $this->put("/api/admin/website-categories/{$theme->id}", [
            'preview_image' => UploadedFile::fake()->image('preview.jpg', 300, 200),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Preview tema berhasil diperbarui.')
            ->assertJsonPath('data.slug', 'lavender-bloom')
            ->assertJsonPath('data.nama_kategori', 'Lavender Bloom')
            ->assertJsonPath('data.preview_image', fn ($url) => is_string($url) && str_contains($url, '/storage/theme-images/previews/lavender-bloom-'))
            ->assertJsonPath('data.thumbnail_image', fn ($url) => is_string($url) && str_contains($url, '/storage/theme-images/previews/lavender-bloom-'))
            ->assertJsonPath('data.preview', fn ($url) => is_string($url) && str_contains($url, '/storage/theme-images/previews/lavender-bloom-'))
            ->assertJsonPath('data.image', fn ($url) => is_string($url) && str_contains($url, '/storage/theme-images/previews/lavender-bloom-'))
            ->assertJsonPath('data.updated_at', fn ($value) => ! empty($value));

        $theme->refresh();
        $storedUrl = $theme->getRawOriginal('preview_image');
        $storedPath = ltrim((string) parse_url($storedUrl, PHP_URL_PATH), '/');
        $storedPath = preg_replace('#^storage/#', '', $storedPath);

        $this->assertSame('Lavender Bloom', $theme->name);
        $this->assertSame($categoryId, $theme->category_id);
        $this->assertNotNull($storedUrl);
        $this->assertStringStartsWith(config('app.url') . '/storage/theme-images/previews/lavender-bloom-', $storedUrl);
        $this->assertStringEndsNotWith('/storage/theme-images/previews/lavender-bloom.jpg', $storedUrl);
        Storage::disk('public')->assertExists($storedPath);
        $this->assertSame($theme->getRawOriginal('preview_image'), $theme->getRawOriginal('image'));
        $this->assertSame($theme->getRawOriginal('preview_image'), $theme->getRawOriginal('thumbnail_image'));
        $this->assertSame($theme->getRawOriginal('preview_image'), $theme->getRawOriginal('preview'));
        $this->assertSame($themeCount, JenisThemas::count());
        $this->assertSame($categoryCount, CategoryThemas::count());
    }

    public function test_admin_preview_image_upload_validation_returns_json(): void
    {
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'soft-ivory')->firstOrFail();

        $this->put("/api/admin/website-categories/{$theme->id}", [
            'preview_image' => UploadedFile::fake()->create('preview.gif', 100, 'image/gif'),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('preview_image');

        $this->put("/api/admin/website-categories/{$theme->id}", [
            'image' => UploadedFile::fake()->image('large-preview.jpg')->size(5121),
        ], [
            'Accept' => 'application/json',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_admin_rejects_empty_nama_kategori_when_field_is_sent(): void
    {
        Sanctum::actingAs($this->adminUser());
        $theme = JenisThemas::where('slug', 'soft-ivory')->firstOrFail();

        $this->putJson("/api/admin/website-categories/{$theme->id}", [
            'nama_kategori' => '',
            'is_active' => false,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nama_kategori');

        $this->assertDatabaseHas('jenis_themas', [
            'id' => $theme->id,
            'name' => 'Soft Ivory',
            'is_active' => true,
        ]);
    }

    public function test_diamond_user_can_select_champagne_rose_and_diamond_garden(): void
    {
        $user = $this->userWithPackage('diamond');
        Sanctum::actingAs($user);

        foreach (['champagne-rose', 'diamond-garden'] as $slug) {
            $theme = JenisThemas::where('slug', $slug)->firstOrFail();

            $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
                ->assertOk()
                ->assertJsonPath('status', true)
                ->assertJsonPath('data.theme.slug', $slug)
                ->assertJsonPath('data.theme.can_use', true)
                ->assertJsonPath('data.theme.locked', false);
        }
    }

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Admin Website',
            'email' => 'admin-website@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function userWithPackage(string $code): User
    {
        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user = User::create([
            'name' => 'Diamond User',
            'email' => 'diamond-user@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ])->save();
        $user->assignRole($role);

        $package = PaketUndangan::where('code', $code)->firstOrFail();

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step1',
            'payment_status' => 'paid',
            'domain_expires_at' => now()->addDays(30),
            'payment_confirmed_at' => now(),
            'package_features_snapshot' => [
                'code' => $package->code,
                'name_paket' => $package->name_paket,
            ],
        ]);

        return $user;
    }

    private function seedThemeCatalog(): void
    {
        foreach ([
            'ruby' => 'Paket Ruby',
            'sapphire' => 'Paket Sapphire',
            'diamond' => 'Paket Diamond',
            'trial' => 'Paket Trial',
        ] as $code => $name) {
            PaketUndangan::create([
                'code' => $code,
                'jenis_paket' => $name,
                'name_paket' => $name,
                'price' => 100000,
                'masa_aktif' => 30,
                'halaman_buku' => 10,
                'kirim_wa' => true,
                'bebas_pilih_tema' => $code !== 'trial',
                'kirim_hadiah' => $code === 'diamond',
                'import_data' => true,
            ]);
        }

        $categories = [];
        foreach ([
            'minimalis' => ['Minimalis', 10],
            'floral' => ['Floral', 20],
            'modern' => ['Modern', 30],
            'elegant' => ['Elegant', 40],
            'luxury' => ['Luxury', 50],
        ] as $slug => [$name, $sortOrder]) {
            $categories[$slug] = CategoryThemas::create([
                'name' => $name,
                'slug' => $slug,
                'type' => 'website',
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }

        foreach ([
            'soft-ivory' => ['Soft Ivory', 'minimalis', 10],
            'lavender-bloom' => ['Lavender Bloom', 'floral', 20],
            'garden-whisper' => ['Garden Whisper', 'floral', 30],
            'champagne-rose' => ['Champagne Rose', 'elegant', 40],
            'diamond-garden' => ['Diamond Garden', 'luxury', 50],
        ] as $slug => [$name, $categorySlug, $sortOrder]) {
            JenisThemas::create([
                'category_id' => $categories[$categorySlug]->id,
                'name' => $name,
                'slug' => $slug,
                'price' => 0,
                'preview' => "theme-images/previews/{$slug}.jpg",
                'preview_image' => "theme-images/previews/{$slug}.jpg",
                'thumbnail_image' => "theme-images/thumbnails/{$slug}.jpg",
                'image' => "theme-images/previews/{$slug}.jpg",
                'url_thema' => "/themes/{$slug}",
                'demo_url' => "/themes/{$slug}",
                'is_active' => true,
                'sort_order' => $sortOrder,
            ]);
        }

        $access = [
            'trial' => ['minimalis'],
            'ruby' => ['minimalis', 'floral'],
            'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
            'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
        ];

        foreach ($access as $code => $categorySlugs) {
            $package = PaketUndangan::where('code', $code)->firstOrFail();
            $package->accessibleCategories()->attach(
                collect($categorySlugs)->map(fn ($slug) => $categories[$slug]->id)->all()
            );
        }
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
            $table->string('phone')->nullable();
            $table->string('kode_pemesanan')->nullable();
            $table->rememberToken();
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
            $table->string('code', 32)->nullable()->unique();
            $table->string('jenis_paket');
            $table->string('name_paket');
            $table->decimal('price', 10, 2);
            $table->integer('masa_aktif');
            $table->integer('halaman_buku')->nullable();
            $table->boolean('kirim_wa')->default(false);
            $table->boolean('bebas_pilih_tema')->default(false);
            $table->boolean('kirim_hadiah')->default(false);
            $table->boolean('import_data')->default(false);
            $table->timestamps();
        });

        Schema::create('category_themas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('type')->default('website');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('paket_undangan_category_thema', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paket_undangan_id');
            $table->unsignedBigInteger('category_thema_id');
            $table->timestamps();
        });

        Schema::create('jenis_themas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->string('name');
            $table->string('slug')->nullable();
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('preview')->nullable();
            $table->string('preview_image')->nullable();
            $table->string('thumbnail_image')->nullable();
            $table->string('url_thema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->string('demo_url')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();
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

        Schema::create('result_themas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thema_id')->nullable();
            $table->unsignedBigInteger('jenis_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });
    }
}
