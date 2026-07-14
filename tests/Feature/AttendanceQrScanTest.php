<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceQrScanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_qr_scan_url_marks_guest_attendance_and_list_persists(): void
    {
        $user = $this->createWeddingWithGuest();

        $this->getJson('/api/v1/attendance/list?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('total', 0);

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=abdi-tata',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Kehadiran tamu berhasil dicatat.')
            ->assertJsonPath('data.guest_name', 'Abdi Tata')
            ->assertJsonPath('data.guest_code', 'abdi-tata')
            ->assertJsonPath('data.domain', 'nova-yusril')
            ->assertJsonPath('data.attendance_status', 'hadir')
            ->assertJsonPath('data.already_scanned', false);

        $this->assertDatabaseHas('attendance_scans', [
            'user_id' => $user->id,
            'guest_name' => 'Abdi Tata',
            'guest_identifier' => 'abdi-tata',
        ]);

        $this->assertDatabaseHas('buku_tamus', [
            'user_id' => $user->id,
            'nama' => 'Abdi Tata',
            'status_kehadiran' => 'hadir',
        ]);

        $this->getJson('/api/v1/attendance/list?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.guest_name', 'Abdi Tata')
            ->assertJsonPath('data.0.guest_code', 'abdi-tata')
            ->assertJsonPath('data.0.domain', 'nova-yusril')
            ->assertJsonPath('data.0.status', 'hadir');
    }

    public function test_qr_scan_same_guest_twice_is_idempotent(): void
    {
        $user = $this->createWeddingWithGuest();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=abdi-tata',
        ])->assertOk();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=abdi-tata',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Tamu sudah pernah discan sebelumnya.')
            ->assertJsonPath('data.guest_name', 'Abdi Tata')
            ->assertJsonPath('data.already_scanned', true);

        $this->assertSame(1, DB::table('attendance_scans')->where('user_id', $user->id)->count());
    }

    public function test_qr_scan_without_to_returns_clear_error(): void
    {
        $this->createWeddingWithGuest();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'GUEST_CODE_REQUIRED');
    }

    public function test_qr_scan_wrong_domain_returns_invitation_not_found(): void
    {
        $this->createWeddingWithGuest();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/domain-salah?to=abdi-tata',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'INVITATION_NOT_FOUND');
    }

    public function test_qr_scan_unknown_guest_returns_guest_not_found(): void
    {
        $this->createWeddingWithGuest();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=tamu-salah',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 'GUEST_NOT_FOUND')
            ->assertJsonPath('message', 'Data tamu tidak ditemukan. Pastikan tamu sudah dibuat atau link undangan benar.');
    }

    public function test_qr_scan_export_uses_database_scan_rows(): void
    {
        $this->createWeddingWithGuest();

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=abdi-tata',
        ])->assertOk();

        $response = $this->getJson('/api/v1/attendance/export?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.mime_type', 'text/csv');

        $csv = base64_decode((string) $response->json('data.content'));
        $this->assertStringContainsString('Abdi Tata', $csv);
        $this->assertStringContainsString('abdi-tata', $csv);
        $this->assertStringContainsString('nova-yusril', $csv);
    }

    private function createWeddingWithGuest(): User
    {
        $user = User::create([
            'name' => 'Nova Yusril Owner',
            'email' => 'qr-scan-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => 'nova-yusril',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('acaras')->insert([
            'user_id' => $user->id,
            'nama_acara' => 'Resepsi',
            'tanggal_acara' => now()->toDateString(),
            'start_acara' => '18:00',
            'end_acara' => '20:00',
            'alamat' => 'Gedung Test',
            'link_maps' => 'https://maps.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $guestData = [
            'user_id' => $user->id,
            'guest_name' => 'Abdi Tata',
            'guest_token' => hash('sha256', 'abdi-tata-'.$user->id),
            'domain' => 'nova-yusril',
            'first_visit_at' => now(),
            'attended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (DB::getSchemaBuilder()->hasColumn('wedding_guests', 'guest_code')) {
            $guestData['guest_code'] = 'abdi-tata';
        }

        DB::table('wedding_guests')->insert($guestData);

        return $user;
    }
}
