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
use App\Models\WeddingGuest;
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

        $user = $this->createPublicWeddingUser('theme-selected');

        ResultThemas::create([
            'user_id' => $user->id,
            'jenis_id' => $theme->id,
            'thema_id' => null,
            'selected_at' => now(),
        ]);

        $this->getJson('/api/v1/wedding-profile/couple/theme-selected')
            ->assertOk()
            ->assertJsonPath('data.selected_theme.id', $theme->id)
            ->assertJsonPath('data.selected_theme.slug', 'soft-ivory')
            ->assertJsonPath('data.selected_theme_slug', 'soft-ivory')
            ->assertJsonPath('data.theme_slug', 'soft-ivory')
            ->assertJsonPath('data.selected_theme.name', $theme->name)
            ->assertJsonPath('data.selected_theme.category_slug', $theme->category->slug)
            ->assertJsonPath('data.themes.selected_theme.id', $theme->id)
            ->assertJsonPath('data.themes.selected_theme.slug', 'soft-ivory')
            ->assertJsonPath('data.themes.selected_theme.name', $theme->name);
    }

    public function test_public_wedding_profile_uses_fallback_theme_when_selected_theme_is_missing(): void
    {
        $this->createPublicWeddingUser('theme-missing');

        $this->getJson('/api/v1/wedding-profile/couple/theme-missing')
            ->assertOk()
            ->assertJsonPath('data.selected_theme.slug', fn ($slug) => is_string($slug) && $slug !== '')
            ->assertJsonPath('data.selected_theme.is_fallback', true)
            ->assertJsonPath('data.selected_theme_slug', fn ($slug) => is_string($slug) && $slug !== '')
            ->assertJsonPath('data.theme_slug', fn ($slug) => is_string($slug) && $slug !== '');
    }

    public function test_public_wedding_route_alias_loads_nova_yusril_and_keeps_guest_fallback(): void
    {
        $this->createPublicWeddingUser('nova-yusril');

        $this->getJson('/api/v1/public/wedding/nova-yusril')
            ->assertOk()
            ->assertJsonPath('data.user_info.name', 'Public Wedding User')
            ->assertJsonPath('data.guest_name', 'Tamu Undangan')
            ->assertJsonPath('data.selected_theme_slug', fn ($slug) => is_string($slug) && $slug !== '');

        $this->getJson('/api/v1/public/wedding/nova-yusril?to=yusril-nova')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'yusril nova')
            ->assertJsonPath('data.nama_tamu', 'yusril nova')
            ->assertJsonPath('data.guest.name', 'yusril nova')
            ->assertJsonPath('data.guest.guest_token', null)
            ->assertJsonPath('data.guest.guest_slug', 'yusril-nova');

        $this->getJson('/api/v1/public/wedding/nova-yusril?to=')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'Tamu Undangan')
            ->assertJsonPath('data.nama_tamu', 'Tamu Undangan')
            ->assertJsonPath('data.guest.name', 'Tamu Undangan');
    }

    public function test_public_wedding_resolves_valid_guest_token_for_current_domain(): void
    {
        $user = $this->createPublicWeddingUser('opah-iyus');
        $guest = $this->createWeddingGuest($user, 'opah-iyus', 'yusril dan nova', 'yusril-dan-nova');

        $this->getJson('/api/v1/wedding/opah-iyus?guest='.$guest->guest_token.'&to=yusril-dan-nova')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'yusril dan nova')
            ->assertJsonPath('data.nama_tamu', 'yusril dan nova')
            ->assertJsonPath('data.guest.name', 'yusril dan nova')
            ->assertJsonPath('data.guest.guest_token', $guest->guest_token)
            ->assertJsonPath('data.guest.guest_slug', 'yusril-dan-nova');
    }

    public function test_public_wedding_rejects_guest_token_from_other_invitation(): void
    {
        $this->createPublicWeddingUser('opah-iyus');
        $otherUser = $this->createPublicWeddingUser('domain-lain');
        $otherGuest = $this->createWeddingGuest($otherUser, 'domain-lain', 'Nama Undangan Lain', 'nama-undangan-lain');

        $this->getJson('/api/v1/wedding/opah-iyus?guest='.$otherGuest->guest_token)
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'Tamu Undangan')
            ->assertJsonPath('data.nama_tamu', 'Tamu Undangan')
            ->assertJsonPath('data.guest.name', 'Tamu Undangan')
            ->assertJsonPath('data.guest.guest_token', null);
    }

    public function test_public_wedding_still_supports_legacy_to_query(): void
    {
        $user = $this->createPublicWeddingUser('legacy-to');
        $guest = $this->createWeddingGuest($user, 'legacy-to', 'Yusril dan Nova', 'yusril-dan-nova');

        $this->getJson('/api/v1/wedding/legacy-to?to=yusril-dan-nova')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'Yusril dan Nova')
            ->assertJsonPath('data.nama_tamu', 'Yusril dan Nova')
            ->assertJsonPath('data.guest.name', 'Yusril dan Nova')
            ->assertJsonPath('data.guest.guest_token', $guest->guest_token)
            ->assertJsonPath('data.guest.guest_slug', 'yusril-dan-nova');
    }

    public function test_public_wedding_without_guest_query_uses_default_guest_name(): void
    {
        $this->createPublicWeddingUser('tanpa-query');

        $this->getJson('/api/v1/wedding/tanpa-query')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'Tamu Undangan')
            ->assertJsonPath('data.nama_tamu', 'Tamu Undangan')
            ->assertJsonPath('data.guest.name', 'Tamu Undangan')
            ->assertJsonPath('data.guest.guest_token', null)
            ->assertJsonPath('data.guest.guest_slug', null);
    }

    public function test_public_wedding_domain_not_found_returns_404(): void
    {
        $this->getJson('/api/v1/public/wedding/domain-tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('message', 'Wedding profile not found for this domain.');
    }

    public function test_public_wedding_unconfirmed_payment_returns_clear_403(): void
    {
        $user = $this->createPublicWeddingUser('payment-pending', 'pending', 'MK');

        $this->getJson('/api/v1/public/wedding/payment-pending')
            ->assertForbidden()
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIRMED')
            ->assertJsonPath('message', 'Pembayaran belum dikonfirmasi.');
    }

    private function createPublicWeddingUser(string $domain, string $paymentStatus = 'paid', string $mempelaiStatus = 'SB'): User
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
            'payment_status' => $paymentStatus,
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
            'status' => $mempelaiStatus === 'SB' ? 'Sudah Bayar' : 'Menunggu Konfirmasi',
            'kd_status' => $mempelaiStatus,
        ]);

        return $user;
    }

    private function createWeddingGuest(User $user, string $domain, string $guestName, string $guestCode): WeddingGuest
    {
        return WeddingGuest::create([
            'user_id' => $user->id,
            'guest_name' => $guestName,
            'guest_token' => hash('sha256', $domain.'-'.$guestCode.'-'.$user->id),
            'guest_code' => $guestCode,
            'domain' => $domain,
            'first_visit_at' => now(),
        ]);
    }
}
