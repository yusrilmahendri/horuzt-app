<?php

namespace Tests\Feature;

use App\Models\CategoryThemas;
use App\Models\Invitation;
use App\Models\JenisThemas;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThemeUpgradeModuleTest extends TestCase
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
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createMinimalSchema();
        $this->seedPackagesAndThemes();
    }

    public function test_semua_tema_muncul_untuk_user_paket_rendah(): void
    {
        $user = $this->createUserWithPackage('ruby');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/themes/categories');

        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => $category['jenis_themas'] ?? []);

        $this->assertSame(['Soft Ivory', 'Garden Whisper', 'Blue Sapphire', 'Velvet Mauve'], $themes->pluck('name')->all());
        $this->assertTrue($themes->firstWhere('slug', 'soft-ivory')['can_use']);
        $this->assertFalse($themes->firstWhere('slug', 'garden-whisper')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'garden-whisper')['upgrade_required']);
        $this->assertSame('sapphire', $themes->firstWhere('slug', 'garden-whisper')['target_package']['code']);
        $this->assertFalse($themes->firstWhere('slug', 'blue-sapphire')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'blue-sapphire')['upgrade_required']);
        $this->assertSame('sapphire', $themes->firstWhere('slug', 'blue-sapphire')['target_package']['code']);
        $this->assertFalse($themes->firstWhere('slug', 'velvet-mauve')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'velvet-mauve')['upgrade_required']);
        $this->assertSame('diamond', $themes->firstWhere('slug', 'velvet-mauve')['target_package']['code']);
    }

    public function test_preview_semua_tema_allowed(): void
    {
        $premiumTheme = JenisThemas::where('slug', 'velvet-mauve')->firstOrFail();

        $this->getJson('/api/themes/demo/'.$premiumTheme->id)
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.demo_url', 'https://example.test/velvet');
    }

    public function test_pilih_tema_sesuai_paket_berhasil(): void
    {
        $user = $this->createUserWithPackage('ruby');
        $theme = JenisThemas::where('slug', 'soft-ivory')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.theme.slug', 'soft-ivory');
    }

    public function test_pilih_tema_premium_tanpa_paket_return_403(): void
    {
        $user = $this->createUserWithPackage('ruby');
        $theme = JenisThemas::where('slug', 'velvet-mauve')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'THEME_UPGRADE_REQUIRED')
            ->assertJsonPath('message', 'Tema ini membutuhkan upgrade paket.');
    }

    public function test_upgrade_paket_membuat_invoice_pending(): void
    {
        $user = $this->createUserWithPackage('ruby');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/upgrade-package', ['target_package_code' => 'diamond'])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'pending')
            ->assertJsonPath('data.target_package.code', 'diamond')
            ->assertJsonPath('data.current_package.code', 'ruby');

        $this->assertSame(2, Invitation::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('invitations', [
            'user_id' => $user->id,
            'payment_status' => 'pending',
            'paket_undangan_id' => PaketUndangan::where('code', 'diamond')->value('id'),
        ]);
    }

    public function test_setelah_invoice_confirmed_user_bisa_pakai_tema_paket_baru(): void
    {
        $user = $this->createUserWithPackage('ruby');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/upgrade-package', ['target_package_code' => 'diamond'])
            ->assertCreated();

        $invoice = Invitation::where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->firstOrFail();
        $invoice->update([
            'payment_status' => 'paid',
            'payment_confirmed_at' => now(),
            'domain_expires_at' => now()->addDays(30),
        ]);

        $theme = JenisThemas::where('slug', 'velvet-mauve')->firstOrFail();

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.theme.slug', 'velvet-mauve');
    }

    public function test_user_diamond_can_use_semua_tema_ruby_sapphire_diamond(): void
    {
        $user = $this->createUserWithPackage('diamond');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/themes/categories');

        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => $category['jenis_themas'] ?? []);

        $this->assertSame(
            ['soft-ivory', 'garden-whisper', 'blue-sapphire', 'velvet-mauve'],
            $themes->pluck('slug')->values()->all()
        );
        $this->assertTrue($themes->every(fn ($theme) => $theme['can_preview'] === true));
        $this->assertTrue($themes->every(fn ($theme) => $theme['admin_is_active'] === true));
        $this->assertTrue($themes->every(fn ($theme) => $theme['can_use'] === true));
        $this->assertTrue($themes->every(fn ($theme) => $theme['upgrade_required'] === false));
        $this->assertTrue($themes->every(fn ($theme) => $theme['inactive_by_admin'] === false));
        $this->assertTrue($themes->every(fn ($theme) => $theme['locked'] === false));
        $this->assertSame(['ruby', 'sapphire', 'sapphire', 'diamond'], $themes->pluck('package_required.code')->values()->all());
    }

    public function test_user_sapphire_bisa_pakai_ruby_dan_sapphire_tapi_tidak_diamond(): void
    {
        $user = $this->createUserWithPackage('sapphire');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/themes/categories');
        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => $category['jenis_themas'] ?? []);

        $this->assertTrue($themes->firstWhere('slug', 'soft-ivory')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'garden-whisper')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'blue-sapphire')['can_use']);
        $this->assertFalse($themes->firstWhere('slug', 'velvet-mauve')['can_use']);
        $this->assertTrue($themes->firstWhere('slug', 'velvet-mauve')['upgrade_required']);
        $this->assertSame('diamond', $themes->firstWhere('slug', 'velvet-mauve')['target_package']['code']);
    }

    public function test_tema_nonaktif_admin_tidak_bisa_dipilih_walaupun_paket_sesuai(): void
    {
        $user = $this->createUserWithPackage('diamond');
        $theme = JenisThemas::where('slug', 'velvet-mauve')->firstOrFail();
        $theme->update(['is_active' => false]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/themes/categories');
        $response->assertOk();
        $themes = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => $category['jenis_themas'] ?? []);
        $inactiveTheme = $themes->firstWhere('slug', 'velvet-mauve');

        $this->assertTrue($inactiveTheme['can_preview']);
        $this->assertFalse($inactiveTheme['admin_is_active']);
        $this->assertFalse($inactiveTheme['can_use']);
        $this->assertTrue($inactiveTheme['locked']);
        $this->assertFalse($inactiveTheme['upgrade_required']);
        $this->assertTrue($inactiveTheme['inactive_by_admin']);
        $this->assertNull($inactiveTheme['target_package']);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'THEME_INACTIVE');
    }

    public function test_user_diamond_bisa_pilih_garden_whisper_dan_profile_mengembalikan_selected_theme(): void
    {
        $user = $this->createUserWithPackage('diamond');
        $theme = JenisThemas::where('slug', 'garden-whisper')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.theme.slug', 'garden-whisper')
            ->assertJsonPath('data.theme.can_use', true)
            ->assertJsonPath('data.theme.locked', false);

        $this->assertDatabaseHas('result_themas', [
            'user_id' => $user->id,
            'jenis_id' => $theme->id,
        ]);

        $this->getJson('/api/v1/user-profile')
            ->assertOk()
            ->assertJsonPath('data.package_code', 'diamond')
            ->assertJsonPath('data.nama_paket', 'Paket Diamond')
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.selected_theme_id', $theme->id)
            ->assertJsonPath('data.selected_theme_slug', 'garden-whisper');

        $response = $this->getJson('/api/themes/categories');
        $response->assertOk();

        $themes = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => $category['jenis_themas'] ?? []);

        $this->assertTrue($themes->firstWhere('slug', 'garden-whisper')['is_current_theme']);
    }

    public function test_legacy_user_jenis_themas_mengirim_status_locked_untuk_diamond(): void
    {
        $user = $this->createUserWithPackage('diamond');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user/jenis-themas');

        $response->assertOk();

        $themes = collect($response->json('data'));

        $this->assertTrue($themes->firstWhere('slug', 'garden-whisper')['can_use']);
        $this->assertFalse($themes->firstWhere('slug', 'garden-whisper')['locked']);
    }

    public function test_user_pending_tidak_bisa_select_tema(): void
    {
        $user = $this->createUserWithPackage('ruby', 'pending', null);
        $theme = JenisThemas::where('slug', 'soft-ivory')->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertForbidden()
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIRMED');
    }

    private function createUserWithPackage(string $code, string $paymentStatus = 'paid', $paymentConfirmedAt = null): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
        $user->assignRole('user');

        $package = PaketUndangan::where('code', $code)->firstOrFail();

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step1',
            'payment_status' => $paymentStatus,
            'is_trial' => $code === 'trial',
            'domain_expires_at' => now()->addDays(30),
            'payment_confirmed_at' => $paymentConfirmedAt ?? ($paymentStatus === 'paid' ? now() : null),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'code' => $package->code,
                'name_paket' => $package->name_paket,
            ],
        ]);

        return $user;
    }

    private function seedPackagesAndThemes(): void
    {
        $ruby = PaketUndangan::create([
            'code' => 'ruby',
            'jenis_paket' => 'Paket Ruby',
            'name_paket' => 'Paket Ruby',
            'price' => 100000,
            'masa_aktif' => 30,
            'halaman_buku' => 10,
            'kirim_wa' => false,
            'bebas_pilih_tema' => true,
            'kirim_hadiah' => true,
            'import_data' => false,
        ]);

        $diamond = PaketUndangan::create([
            'code' => 'diamond',
            'jenis_paket' => 'Paket Diamond',
            'name_paket' => 'Paket Diamond',
            'price' => 300000,
            'masa_aktif' => 30,
            'halaman_buku' => 30,
            'kirim_wa' => true,
            'bebas_pilih_tema' => true,
            'kirim_hadiah' => true,
            'import_data' => true,
        ]);

        $sapphire = PaketUndangan::create([
            'code' => 'sapphire',
            'jenis_paket' => 'Paket Sapphire',
            'name_paket' => 'Paket Sapphire',
            'price' => 200000,
            'masa_aktif' => 30,
            'halaman_buku' => 20,
            'kirim_wa' => true,
            'bebas_pilih_tema' => true,
            'kirim_hadiah' => true,
            'import_data' => false,
        ]);

        $minimalis = CategoryThemas::create([
            'name' => 'Minimalis',
            'slug' => 'minimalis',
            'type' => 'website',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $floral = CategoryThemas::create([
            'name' => 'Floral',
            'slug' => 'floral',
            'type' => 'website',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $luxury = CategoryThemas::create([
            'name' => 'Luxury',
            'slug' => 'luxury',
            'type' => 'website',
            'is_active' => true,
            'sort_order' => 4,
        ]);
        $modern = CategoryThemas::create([
            'name' => 'Modern',
            'slug' => 'modern',
            'type' => 'website',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $ruby->accessibleCategories()->attach([$minimalis->id, $floral->id]);
        $sapphire->accessibleCategories()->attach([$minimalis->id, $floral->id, $modern->id]);
        $diamond->accessibleCategories()->attach([$minimalis->id, $floral->id, $modern->id, $luxury->id]);

        JenisThemas::create([
            'category_id' => $minimalis->id,
            'name' => 'Soft Ivory',
            'slug' => 'soft-ivory',
            'price' => 0,
            'preview' => 'soft.jpg',
            'url_thema' => 'soft-theme',
            'demo_url' => 'https://example.test/soft',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        JenisThemas::create([
            'category_id' => $luxury->id,
            'name' => 'Velvet Mauve',
            'slug' => 'velvet-mauve',
            'price' => 0,
            'preview' => 'velvet.jpg',
            'url_thema' => 'velvet-theme',
            'demo_url' => 'https://example.test/velvet',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        JenisThemas::create([
            'category_id' => $floral->id,
            'name' => 'Garden Whisper',
            'slug' => 'garden-whisper',
            'price' => 0,
            'preview' => 'garden.jpg',
            'url_thema' => 'garden-theme',
            'demo_url' => 'https://example.test/garden',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        JenisThemas::create([
            'category_id' => $modern->id,
            'name' => 'Blue Sapphire',
            'slug' => 'blue-sapphire',
            'price' => 0,
            'preview' => 'sapphire.jpg',
            'url_thema' => 'sapphire-theme',
            'demo_url' => 'https://example.test/sapphire',
            'is_active' => true,
            'sort_order' => 2,
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
            $table->string('kode_pemesanan')->nullable();
            $table->unsignedBigInteger('paket_undangan_id');
            $table->string('status')->default('step1');
            $table->string('payment_status')->default('pending');
            $table->boolean('is_trial')->default(false);
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->decimal('package_price_snapshot', 10, 2)->nullable();
            $table->integer('package_duration_snapshot')->nullable();
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

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('domain')->nullable();
            $table->timestamps();
        });

        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->nullable();
            $table->string('kd_status')->nullable();
            $table->timestamps();
        });
    }
}
