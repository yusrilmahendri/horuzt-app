<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWebsiteThemeConnectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_website_theme_endpoint_returns_fixed_theme_slugs_with_existing_ids(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'Admin Theme',
            'email' => 'admin-theme@example.test',
            'password' => 'secret123',
        ]);
        $admin->assignRole($adminRole);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/themes?type=website&per_page=50');

        $response->assertOk()->assertJsonPath('status', true);

        $themes = collect($response->json('data.data'));
        $expectedSlugs = [
            'soft-ivory',
            'lavender-bloom',
            'garden-whisper',
            'modern-vows',
            'champagne-rose',
            'velvet-mauve',
        ];

        foreach ($expectedSlugs as $slug) {
            $theme = $themes->firstWhere('slug', $slug);

            $this->assertNotNull($theme, "Theme {$slug} tidak ditemukan di endpoint admin.");
            $this->assertNotEmpty($theme['id'] ?? null, "Theme {$slug} belum terhubung ke id backend.");
        }
    }
}
