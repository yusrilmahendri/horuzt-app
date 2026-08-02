<?php

namespace Tests\Feature;

use App\Models\Galery;
use App\Models\Invitation;
use App\Models\JenisThemas;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\ResultThemas;
use App\Models\Setting;
use App\Models\User;
use App\Models\WeddingGuest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
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

    public function test_public_wedding_share_returns_safe_open_graph_html_with_cover_photo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/cover-sena.jpg', 'fake image content');

        $this->createPublicWeddingUser('share-cover');

        Mempelai::whereHas('user.settingOne', fn ($query) => $query->where('domain', 'share-cover'))
            ->update([
                'cover_photo' => 'photos/cover-sena.jpg',
                'name_panggilan_pria' => 'Sena',
                'name_panggilan_wanita' => 'Digital',
            ]);

        Setting::where('domain', 'share-cover')->update([
            'religion_invitation_intro' => '<p>Dengan bahagia kami mengundang {{guest_name}} untuk hadir.</p>',
        ]);

        $response = $this->get('/api/v1/public/wedding/share-cover/share?guest=secret-token&to=tamu-rahasia');

        $response->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $html = $response->getContent();

        $this->assertStringContainsString('<meta property="og:title" content="The Wedding of Sena &amp; Digital">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="https://www.sena-digital.com/wedding/share-cover">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://www.sena-digital.com/wedding/share-cover">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="https://cloud-api.sena-digital.com/storage/photos/cover-sena.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:image:secure_url" content="https://cloud-api.sena-digital.com/storage/photos/cover-sena.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:image:type" content="image/jpeg">', $html);
        $this->assertStringContainsString('Dengan bahagia kami mengundang untuk hadir.', $html);
        $this->assertStringNotContainsString('secret-token', $html);
        $this->assertStringNotContainsString('tamu-rahasia', $html);
        $this->assertStringNotContainsString('og:image:width', $html);
        $this->assertStringNotContainsString('og:image:height', $html);
    }

    public function test_public_wedding_share_normalizes_legacy_api_storage_image_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('photos/cover sena.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        $this->createPublicWeddingUser('share-normalized-cover');

        Mempelai::whereHas('user.settingOne', fn ($query) => $query->where('domain', 'share-normalized-cover'))
            ->update([
                'cover_photo' => 'https://www.sena-digital.com/api/storage/photos/cover%20sena.png',
            ]);

        $this->get('/api/v1/public/wedding/share-normalized-cover/share')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://cloud-api.sena-digital.com/storage/photos/cover%20sena.png">', false)
            ->assertSee('<meta property="og:image:secure_url" content="https://cloud-api.sena-digital.com/storage/photos/cover%20sena.png">', false)
            ->assertSee('<meta property="og:image:type" content="image/png">', false)
            ->assertSee('<meta property="og:image:width" content="2">', false)
            ->assertSee('<meta property="og:image:height" content="1">', false)
            ->assertDontSee('https://www.sena-digital.com/api/storage', false);
    }

    public function test_public_wedding_share_uses_featured_gallery_when_cover_photo_missing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/featured.jpg', 'fake image content');
        Storage::disk('public')->put('gallery/ordinary.jpg', 'fake image content');

        $user = $this->createPublicWeddingUser('share-gallery');

        Galery::create([
            'user_id' => $user->id,
            'photo' => 'gallery/ordinary.jpg',
            'status' => true,
            'is_featured' => false,
            'sort_order' => 1,
            'mime_type' => 'image/jpeg',
        ]);

        Galery::create([
            'user_id' => $user->id,
            'photo' => 'gallery/featured.jpg',
            'status' => true,
            'is_featured' => true,
            'sort_order' => 2,
            'mime_type' => 'image/jpeg',
        ]);

        $this->get('/api/v1/public/wedding/share-gallery/share')
            ->assertOk()
            ->assertSee('https://cloud-api.sena-digital.com/storage/gallery/featured.jpg', false)
            ->assertDontSee('https://cloud-api.sena-digital.com/storage/gallery/ordinary.jpg', false);
    }

    public function test_public_wedding_share_skips_unsafe_frontend_and_signed_image_candidates(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/safe-cover.jpg', 'fake image content');

        $user = $this->createPublicWeddingUser('share-unsafe-cover');

        Mempelai::whereHas('user.settingOne', fn ($query) => $query->where('domain', 'share-unsafe-cover'))
            ->update([
                'cover_photo' => 'https://www.sena-digital.com/assets/images/cover.jpg?guest=secret-token',
            ]);

        Galery::create([
            'user_id' => $user->id,
            'photo' => 'gallery/safe-cover.jpg',
            'status' => true,
            'is_featured' => true,
            'mime_type' => 'image/jpeg',
        ]);

        $this->get('/api/v1/public/wedding/share-unsafe-cover/share?guest=secret-token')
            ->assertOk()
            ->assertSee('https://cloud-api.sena-digital.com/storage/gallery/safe-cover.jpg', false)
            ->assertDontSee('https://www.sena-digital.com/assets/images/cover.jpg', false)
            ->assertDontSee('secret-token', false);
    }

    public function test_public_wedding_share_returns_404_when_invitation_cannot_be_shared(): void
    {
        $this->createPublicWeddingUser('share-pending', 'pending', 'MK');

        $this->get('/api/v1/public/wedding/share-pending/share')
            ->assertNotFound();
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

    public function test_public_wedding_does_not_resolve_deleted_guest_token(): void
    {
        $user = $this->createPublicWeddingUser('deleted-token');
        $guest = $this->createWeddingGuest($user, 'deleted-token', 'Tamu Dihapus', 'tamu-dihapus');
        $token = $guest->guest_token;

        $guest->delete();

        $this->getJson('/api/v1/wedding/deleted-token?guest='.$token.'&to=tamu-dihapus')
            ->assertOk()
            ->assertJsonPath('data.guest_name', 'tamu dihapus')
            ->assertJsonPath('data.nama_tamu', 'tamu dihapus')
            ->assertJsonPath('data.guest.name', 'tamu dihapus')
            ->assertJsonPath('data.guest.guest_token', null)
            ->assertJsonPath('data.guest.guest_slug', 'tamu-dihapus');
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
