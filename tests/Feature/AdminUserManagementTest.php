<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_list_users_with_status_filter(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $this->createUserWithInvitationStatus('active-user@example.test', now()->addDays(15), 'active-domain');
        $this->createUserWithInvitationStatus('soon-user@example.test', now()->addDays(3), 'soon-domain');
        $this->createUserWithInvitationStatus('expired-user@example.test', now()->subDay(), 'expired-domain');

        $response = $this->getJson('/api/v1/admin/users?status=expiring_soon&expiring_within_days=7&sort_by=domain_expires_at&sort_order=asc');

        $response->assertOk()
            ->assertJsonPath('message', 'Data pengguna berhasil diambil.')
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.status_akun', 'expiring_soon')
            ->assertJsonPath('data.0.domain', 'soon-domain');
    }

    public function test_admin_can_manually_upgrade_user_package_without_creating_new_invoice(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $ruby = $this->createPackageWithCode('ruby');
        $diamond = $this->createPackageWithCode('diamond');
        $user = User::create([
            'name' => 'Manual Upgrade User',
            'email' => 'manual-upgrade@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $invitation = Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $ruby->id,
            'kode_pemesanan' => 'INV-MANUAL-001',
            'status' => 'step3',
            'payment_status' => 'pending',
            'domain_expires_at' => null,
            'payment_confirmed_at' => null,
            'package_features_snapshot' => [
                'code' => 'ruby',
                'name_paket' => 'Paket Ruby',
            ],
        ]);
        $invoiceCount = Invitation::count();

        $this->postJson("/api/v1/admin/users/{$user->id}/upgrade-package", [
            'package_code' => 'diamond',
            'expired_at' => '2026-07-30',
            'note' => 'Upgrade manual oleh admin',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Paket pengguna berhasil diperbarui.')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.package_code', 'diamond')
            ->assertJsonPath('data.package_name', 'Paket Diamond')
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.payment_status', 'confirmed')
            ->assertJsonPath('data.active_until', '2026-07-30')
            ->assertJsonPath('data.active_until_formatted', '30/07/2026');

        $invitation->refresh();

        $this->assertSame($invoiceCount, Invitation::count());
        $this->assertSame($diamond->id, $invitation->paket_undangan_id);
        $this->assertSame('paid', $invitation->payment_status);
        $this->assertSame('2026-07-30', $invitation->domain_expires_at->toDateString());
        $this->assertNotNull($invitation->payment_confirmed_at);
        $this->assertSame('diamond', $invitation->package_features_snapshot['code']);
        $this->assertSame('Paket Diamond', $invitation->package_features_snapshot['name_paket']);
        $this->assertSame('Upgrade manual oleh admin', $invitation->package_features_snapshot['manual_upgrade_note']);

        $this->assertDatabaseHas('payment_logs', [
            'user_id' => $user->id,
            'invitation_id' => $invitation->id,
            'payment_type' => 'manual_admin_upgrade',
            'notes' => 'Upgrade manual oleh admin',
        ]);
    }

    public function test_admin_can_soft_delete_user_data_without_deleting_account(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $packageId = $this->createPackage();
        $user = User::create([
            'name' => 'Soft Delete User',
            'email' => 'soft-delete@example.test',
            'password' => bcrypt('secret123'),
            'profile_photo' => 'profiles/missing-photo.jpg',
            'kode_pemesanan' => 'SOFT-001',
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => 'soft-domain',
            'musik' => 'music/missing-soft.mp3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('mempelais')->insert([
            'user_id' => $user->id,
            'cover_photo' => 'mempelai/cover-missing.jpg',
            'photo_pria' => 'mempelai/pria-missing.jpg',
            'photo_wanita' => 'mempelai/wanita-missing.jpg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $countdownId = DB::table('countdown_acaras')->insertGetId([
            'user_id' => $user->id,
            'name_countdown' => 'Hitung Mundur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('acaras')->insert([
            'user_id' => $user->id,
            'countdown_id' => $countdownId,
            'nama_acara' => 'Akad',
            'tanggal_acara' => now()->toDateString(),
            'start_acara' => '08:00',
            'end_acara' => '10:00',
            'alamat' => 'Alamat Test',
            'link_maps' => 'https://maps.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('galeries')->insert([
            'user_id' => $user->id,
            'photo' => 'galery/missing-photo.jpg',
            'file_path' => 'galery/missing-file-path.jpg',
            'photo_type' => 'gallery',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ceritas')->insert([
            'user_id' => $user->id,
            'title' => 'Cerita Test',
            'lead_cerita' => 'Isi cerita',
            'tanggal_cerita' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('qoutes')->insert([
            'user_id' => $user->id,
            'name' => 'Pengantin',
            'qoute' => 'Qoute test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('buku_tamus')->insert([
            'user_id' => $user->id,
            'nama' => 'Tamu Test',
            'email' => 'tamu@example.test',
            'telepon' => '081234567890',
            'ucapan' => 'Selamat!',
            'status_kehadiran' => 'hadir',
            'jumlah_tamu' => 2,
            'is_approved' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ucapans')->insert([
            'user_id' => $user->id,
            'nama' => 'Ucapan Test',
            'pesan' => 'Semoga bahagia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('wedding_guests')->insert([
            'user_id' => $user->id,
            'guest_name' => 'Guest Track',
            'guest_token' => hash('sha256', 'guest-track-soft-delete'),
            'domain' => 'soft-domain',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('filter_undangans')->insert([
            'user_id' => $user->id,
            'halaman_sampul' => 1,
            'halaman_mempelai' => 1,
            'halaman_acara' => 1,
            'halaman_ucapan' => 1,
            'halaman_galery' => 1,
            'halaman_cerita' => 1,
            'halaman_lokasi' => 1,
            'halaman_prokes' => 1,
            'halaman_send_gift' => 1,
            'halaman_qoute' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invitationId = DB::table('invitations')->insertGetId([
            'user_id' => $user->id,
            'paket_undangan_id' => $packageId,
            'status' => 'completed',
            'payment_status' => 'paid',
            'domain_expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('komentars')->insert([
            'invitation_id' => $invitationId,
            'nama' => 'Komentator',
            'komentar' => 'Komentar test',
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}/soft-data");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Data pengguna berhasil dibersihkan.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('settings', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('mempelais', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('acaras', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('countdown_acaras', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('galeries', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('ceritas', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('qoutes', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('buku_tamus', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('ucapans', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('wedding_guests', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('filter_undangans', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('komentars', ['invitation_id' => $invitationId]);

        $this->assertDatabaseHas('invitations', ['id' => $invitationId]);
    }

    public function test_frontend_soft_delete_route_exists_and_cleans_user_data(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::create([
            'name' => 'Frontend Soft Delete User',
            'email' => 'frontend-soft-delete@example.test',
            'password' => bcrypt('secret123'),
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => 'frontend-soft-delete',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/admin/get-users/{$user->id}/soft-delete-data");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Data pengguna berhasil dibersihkan.');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('settings', ['user_id' => $user->id]);
    }

    public function test_admin_can_hard_delete_user_and_all_related_data(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $userRole = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $packageId = $this->createPackage();

        $user = User::create([
            'name' => 'Hard Delete User',
            'email' => 'hard-delete@example.test',
            'password' => bcrypt('secret123'),
        ]);
        $user->assignRole($userRole);

        $invitationId = DB::table('invitations')->insertGetId([
            'user_id' => $user->id,
            'paket_undangan_id' => $packageId,
            'status' => 'completed',
            'payment_status' => 'paid',
            'domain_expires_at' => now()->addDays(5),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_logs')->insert([
            'user_id' => $user->id,
            'invitation_id' => $invitationId,
            'event_type' => 'webhook_received',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paketNikahId = DB::table('paket_nikahs')->insertGetId([
            'name' => 'Paket Nikah Test',
            'price' => '100000',
            'masa_aktif' => '30',
            'buku_tamu' => '1',
            'kirim_wa' => '1',
            'kirim_hadiah' => '1',
            'tema_bebas' => '1',
            'import_data' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'user_id' => $user->id,
            'paket_id' => $paketNikahId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('midtrans_transactions')->insert([
            'user_id' => $user->id,
            'url' => 'https://midtrans.example.test',
            'server_key' => 'server-key',
            'client_key' => 'client-key',
            'metode_production' => 'sandbox',
            'methode_pembayaran' => 'bank_transfer',
            'id_methode_pembayaran' => 'bca',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tripay_transactions')->insert([
            'user_id' => $user->id,
            'url_tripay' => 'https://tripay.example.test',
            'private_key' => 'private-key',
            'api_key' => 'api-key',
            'kode_merchant' => 'MERCHANT-1',
            'methode_pembayaran' => 'bank_transfer',
            'id_methode_pembayaran' => 'bca',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/admin/users/{$user->id}");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Akun pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('invitations', ['id' => $invitationId]);
        $this->assertDatabaseMissing('payment_logs', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('orders', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('midtrans_transactions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('tripay_transactions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    public function test_frontend_hard_delete_route_exists_and_deletes_user(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $user = User::create([
            'name' => 'Frontend Hard Delete User',
            'email' => 'frontend-hard-delete@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->deleteJson("/api/v1/admin/get-users/{$user->id}/hard-delete");

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Akun pengguna berhasil dihapus.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_user_not_found_returns_clear_404(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $this->deleteJson('/api/v1/admin/get-users/999999/hard-delete')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pengguna tidak ditemukan.');

        $this->postJson('/api/v1/admin/get-users/999999/soft-delete-data')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pengguna tidak ditemukan.');
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->createAdminUser();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/admin/get-users/{$admin->id}/hard-delete")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Admin tidak dapat menghapus akun sendiri.');

        $this->postJson("/api/v1/admin/get-users/{$admin->id}/soft-delete-data")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Admin tidak dapat menghapus akun sendiri.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function createAdminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name' => 'Admin QA',
            'email' => 'admin-user-management@example.test',
            'password' => bcrypt('secret123'),
        ]);

        $admin->assignRole($adminRole);

        return $admin;
    }

    private function createPackage(): int
    {
        return (int) DB::table('paket_undangans')->insertGetId([
            'jenis_paket' => 'Premium',
            'name_paket' => 'Paket Premium',
            'price' => 200000,
            'masa_aktif' => 30,
            'halaman_buku' => 10,
            'kirim_wa' => true,
            'bebas_pilih_tema' => true,
            'kirim_hadiah' => true,
            'import_data' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createPackageWithCode(string $code): PaketUndangan
    {
        return PaketUndangan::query()->updateOrCreate(
            ['code' => $code],
            [
                'jenis_paket' => PaketUndangan::jenisPaketFromCode($code),
                'name_paket' => PaketUndangan::displayLabelFromCode($code),
                'price' => $code === 'diamond' ? 300000 : 100000,
                'masa_aktif' => 30,
                'halaman_buku' => 10,
                'kirim_wa' => true,
                'bebas_pilih_tema' => $code !== 'trial',
                'kirim_hadiah' => $code === 'diamond',
                'import_data' => true,
            ]
        );
    }

    private function createUserWithInvitationStatus(string $email, \Illuminate\Support\Carbon $expiryAt, string $domain): User
    {
        $packageId = $this->createPackage();
        $user = User::create([
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('secret123'),
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => $domain,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invitations')->insert([
            'user_id' => $user->id,
            'paket_undangan_id' => $packageId,
            'status' => 'completed',
            'payment_status' => 'paid',
            'domain_expires_at' => $expiryAt,
            'payment_confirmed_at' => now()->subDays(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }
}
