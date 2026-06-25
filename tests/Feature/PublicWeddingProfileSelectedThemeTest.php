<?php

namespace Tests\Feature;

use App\Models\CategoryThemas;
use App\Models\Invitation;
use App\Models\JenisThemas;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\ResultThemas;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicWeddingProfileSelectedThemeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_wedding_profile_returns_selected_theme_slug(): void
    {
        $theme = JenisThemas::where('slug', 'soft-ivory')
            ->with('category')
            ->firstOrFail();

        $user = $this->createPublicWeddingUser('theme-selected.test');

        ResultThemas::create([
            'user_id' => $user->id,
            'jenis_id' => $theme->id,
            'thema_id' => null,
            'selected_at' => now(),
        ]);

        $this->getJson('/api/v1/wedding-profile/couple/theme-selected.test')
            ->assertOk()
            ->assertJsonPath('data.selected_theme.id', $theme->id)
            ->assertJsonPath('data.selected_theme.slug', 'soft-ivory')
            ->assertJsonPath('data.selected_theme.name', $theme->name)
            ->assertJsonPath('data.selected_theme.category_slug', $theme->category->slug)
            ->assertJsonPath('data.themes.selected_theme.id', $theme->id)
            ->assertJsonPath('data.themes.selected_theme.slug', 'soft-ivory')
            ->assertJsonPath('data.themes.selected_theme.name', $theme->name);
    }

    public function test_public_wedding_profile_returns_null_when_selected_theme_is_missing(): void
    {
        $user = $this->createPublicWeddingUser('theme-missing.test');

        $this->getJson('/api/v1/wedding-profile/couple/theme-missing.test')
            ->assertOk()
            ->assertJsonPath('data.selected_theme', null);
    }

    private function createPublicWeddingUser(string $domain): User
    {
        $user = User::create([
            'name' => 'Public Wedding User',
            'email' => $domain.'@example.test',
            'password' => 'secret123',
            'phone' => '08123456789',
        ]);

        $package = PaketUndangan::where('code', 'ruby')->firstOrFail();

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step3',
            'payment_status' => 'paid',
            'is_trial' => false,
            'kode_pemesanan' => 'INV-'.$user->id,
            'domain_expires_at' => now()->addDays(30),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'name_paket' => $package->name_paket,
            ],
        ]);

        Setting::create([
            'user_id' => $user->id,
            'domain' => $domain,
        ]);

        Mempelai::create([
            'user_id' => $user->id,
            'name_lengkap_pria' => 'Anton',
            'name_lengkap_wanita' => 'Keok',
            'name_panggilan_pria' => 'anton',
            'name_panggilan_wanita' => 'keok',
            'status' => 'Sudah Bayar',
            'kd_status' => 'SB',
        ]);

        return $user;
    }
}
