<?php

namespace Tests\Feature;

use App\Models\CategoryThemas;
use App\Models\Invitation;
use App\Models\JenisThemas;
use App\Models\PaketUndangan;
use App\Models\ResultThemas;
use App\Models\User;
use App\Services\PackageThemeAccessService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PackageThemeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_package_has_the_expected_cumulative_category_access(): void
    {
        $expected = [
            'trial' => ['minimalis'],
            'ruby' => ['minimalis', 'floral'],
            'sapphire' => ['minimalis', 'floral', 'modern', 'elegant'],
            'diamond' => ['minimalis', 'floral', 'modern', 'elegant', 'luxury'],
        ];

        foreach ($expected as $code => $slugs) {
            $user = $this->createUserWithPackage($code);
            $actual = app(PackageThemeAccessService::class)
                ->accessibleCategories($user)
                ->pluck('slug')
                ->all();

            $this->assertSame($slugs, $actual, "Unexpected category access for {$code}");
        }
    }

    public function test_package_theme_backfill_is_idempotent_and_preserves_package_ids(): void
    {
        $packageIds = PaketUndangan::orderBy('code')->pluck('id', 'code')->all();

        (require database_path('migrations/2026_06_22_000002_backfill_package_theme_category_access.php'))->up();

        $this->assertSame($packageIds, PaketUndangan::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame(4, PaketUndangan::whereNotNull('code')->count());
        $this->assertSame(5, DB::table('category_themas')->count());
        $this->assertSame(6, DB::table('jenis_themas')->count());
        $this->assertSame(12, DB::table('paket_undangan_category_thema')->count());
    }

    public function test_trial_registration_is_paid_and_expires_after_three_days(): void
    {
        Carbon::setTestNow('2026-06-22 10:00:00');

        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $trial = PaketUndangan::where('code', 'trial')->firstOrFail();

        $response = $this->postJson('/api/v1/one-step', [
            'email' => 'trial@example.test',
            'password' => 'secret123',
            'phone' => '08123456789',
            'paket_undangan_id' => $trial->id,
            'domain' => 'trial-example',
        ]);

        $response->assertCreated();

        $invitation = Invitation::whereHas('user', fn ($query) => $query
            ->where('email', 'trial@example.test'))
            ->firstOrFail();

        $this->assertTrue($invitation->is_trial);
        $this->assertSame('paid', $invitation->payment_status);
        $this->assertSame(3, $trial->masa_aktif);
        $this->assertTrue($invitation->domain_expires_at->equalTo(now()->addDays(3)));

        Carbon::setTestNow();
    }

    public function test_registration_catalog_can_be_read_without_login(): void
    {
        $package = PaketUndangan::where('code', 'ruby')->firstOrFail();

        $this->getJson('/api/v1/paket-undangan')
            ->assertOk()
            ->assertJsonPath('data.0.id', fn ($value) => is_int($value));

        $this->getJson('/api/themes/categories?package_code=ruby')
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->getJson('/api/themes/categories?package_id='.$package->id)
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_public_themes_are_filtered_by_package_code(): void
    {
        $response = $this->getJson('/api/themes/categories?package_code=ruby');

        $response->assertOk()
            ->assertJsonPath('data.total_categories', 2)
            ->assertJsonPath('data.total_themes', 3);

        $categorySlugs = collect($response->json('data.categories'))->pluck('slug')->all();
        $themeNames = collect($response->json('data.categories'))
            ->flatMap(fn ($category) => collect($category['jenis_themas'] ?? [])->pluck('name'))
            ->all();

        $this->assertSame(['minimalis', 'floral'], $categorySlugs);
        $this->assertContains('Soft Ivory', $themeNames);
        $this->assertContains('Lavender Bloom', $themeNames);
        $this->assertContains('Garden Whisper', $themeNames);
        $this->assertNotContains('Modern Vows', $themeNames);
        $this->assertNotContains('Velvet Mauve', $themeNames);
    }

    public function test_theme_selection_without_login_is_rejected(): void
    {
        $theme = JenisThemas::where('slug', 'soft-ivory')->firstOrFail();

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertForbidden();
    }

    public function test_user_cannot_select_a_theme_outside_their_package_categories(): void
    {
        $user = $this->createUserWithPackage('ruby');
        $theme = JenisThemas::where('slug', 'velvet-mauve')->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/themes/select', ['theme_id' => $theme->id])
            ->assertForbidden()
            ->assertJson([
                'status' => false,
                'message' => 'Tema ini tidak tersedia untuk paket Anda.',
            ]);

        $this->assertDatabaseMissing('result_themas', [
            'user_id' => $user->id,
            'jenis_id' => $theme->id,
        ]);
    }

    public function test_authenticated_user_can_only_read_their_own_dashboard_context(): void
    {
        $user = $this->createUserWithPackage('ruby');
        $otherUser = $this->createUserWithPackage('diamond');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/overview/'.$otherUser->id);

        $response->assertOk()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.invitation_package.code', 'ruby');
    }

    private function createUserWithPackage(string $code): User
    {
        $user = User::factory()->create([
            'email' => $code.'-'.uniqid().'@example.test',
        ]);

        $role = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $user->assignRole($role);

        $package = PaketUndangan::where('code', $code)->firstOrFail();

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step1',
            'payment_status' => 'paid',
            'is_trial' => $code === 'trial',
            'domain_expires_at' => now()->addDays($package->masa_aktif),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'name_paket' => $package->name_paket,
                'bebas_pilih_tema' => $package->bebas_pilih_tema,
            ],
        ]);

        return $user;
    }
}
