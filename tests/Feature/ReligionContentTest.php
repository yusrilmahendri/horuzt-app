<?php

namespace Tests\Feature;

use App\Models\Acara;
use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\Pernikahan;
use App\Models\Qoute;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReligionContentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_get_default_religion_content(): void
    {
        $user = $this->actingAsUser();

        Setting::create([
            'user_id' => $user->id,
            'religion_code' => 'islam',
        ]);

        $this->getJson('/api/v1/user/religion-content')
            ->assertOk()
            ->assertJsonPath('message', 'Konten agama berhasil diambil.')
            ->assertJsonPath('data.religion_code', 'islam')
            ->assertJsonPath('data.religion_label', 'Islam')
            ->assertJsonPath('data.resolved.opening_greeting', 'Assalamu\'alaikum Warahmatullahi Wabarakatuh.');
    }

    public function test_user_can_update_custom_content_and_change_religion_without_deleting_custom_values(): void
    {
        $user = $this->actingAsUser();

        $this->putJson('/api/v1/user/religion-content', [
            'religion_code' => 'islam',
            'opening_greeting' => '',
            'whatsapp_message' => 'Halo {{guest_name}}, undangan {{bride_name}} dan {{groom_name}}: {{invitation_url}}',
        ])->assertOk()
            ->assertJsonPath('message', 'Konten agama berhasil diperbarui.')
            ->assertJsonPath('data.custom.opening_greeting', '')
            ->assertJsonPath('data.resolved.opening_greeting', '');

        $this->putJson('/api/v1/user/religion-content', [
            'religion_code' => 'kristen',
        ])->assertOk()
            ->assertJsonPath('data.religion_code', 'christian')
            ->assertJsonPath('data.custom.opening_greeting', '')
            ->assertJsonPath('data.resolved.opening_greeting', '');

        $setting = Setting::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('christian', $setting->religion_code);
        $this->assertSame('', $setting->religion_opening_greeting);
        $this->assertSame(
            'Halo {{guest_name}}, undangan {{bride_name}} dan {{groom_name}}: {{invitation_url}}',
            $setting->religion_whatsapp_message
        );
    }

    public function test_user_can_reset_one_field_and_all_fields(): void
    {
        $user = $this->actingAsUser();

        Setting::create([
            'user_id' => $user->id,
            'religion_code' => 'islam',
            'religion_opening_greeting' => 'Custom pembuka',
            'religion_closing_greeting' => 'Custom penutup',
        ]);

        $this->postJson('/api/v1/user/religion-content/reset', [
            'fields' => ['opening_greeting'],
        ])->assertOk()
            ->assertJsonPath('message', 'Konten agama berhasil direset.')
            ->assertJsonPath('data.custom.opening_greeting', null)
            ->assertJsonPath('data.custom.closing_greeting', 'Custom penutup');

        $this->postJson('/api/v1/user/religion-content/reset')
            ->assertOk()
            ->assertJsonPath('data.custom.closing_greeting', null);
    }

    public function test_whatsapp_placeholder_is_resolved_in_public_invitation_response(): void
    {
        $user = $this->createPublicWeddingUser('agama-placeholder-test');

        Setting::where('user_id', $user->id)->update([
            'religion_code' => 'islam',
            'religion_whatsapp_message' => 'Halo {{guest_name}}, {{bride_name}} dan {{groom_name}} menikah di {{event_location}} pada {{event_date}}. {{invitation_url}}',
        ]);

        $this->getJson('/api/v1/wedding-profile/couple/agama-placeholder-test?guest_name=Budi')
            ->assertOk()
            ->assertJsonPath('data.religion.code', 'islam')
            ->assertJsonPath('data.religion.label', 'Islam')
            ->assertJsonPath('data.religion_content.whatsapp_text', 'Halo Budi, Sari dan Bima menikah di Gedung Bahagia pada 2026-08-01. http://localhost/agama-placeholder-test')
            ->assertJsonPath('data.whatsapp_text', 'Halo Budi, Sari dan Bima menikah di Gedung Bahagia pada 2026-08-01. http://localhost/agama-placeholder-test')
            ->assertJsonPath('data.quote.text', 'Dan di antara tanda-tanda kekuasaan-Nya ialah Dia menciptakan untukmu pasangan hidup dari jenismu sendiri.');
    }

    public function test_legacy_data_is_available_as_fallback_when_new_content_is_missing(): void
    {
        $user = $this->actingAsUser();

        Setting::create([
            'user_id' => $user->id,
            'religion_code' => 'custom',
            'salam_pembuka' => 'Salam legacy',
        ]);
        Pernikahan::create([
            'user_id' => $user->id,
            'nama_panggilan_pria' => 'Bima',
            'nama_panggilan_wanita' => 'Sari',
            'nama_lengkap_pria' => 'Bima Pratama',
            'nama_lengkap_wanita' => 'Sari Ayu',
            'gender_pria' => 'pria',
            'gender_wanita' => 'wanita',
            'alamat' => 'Alamat',
            'video' => 'video.mp4',
            'photo_pria' => 'pria.jpg',
            'photo_wanita' => 'wanita.jpg',
            'tgl_cerita' => '2026-08-01',
            'salam_wa_atas' => 'WA atas legacy',
            'salam_wa_bawah' => 'WA bawah legacy',
        ]);
        $quote = new Qoute();
        $quote->user_id = $user->id;
        $quote->name = 'Sumber legacy';
        $quote->qoute = 'Quote legacy';
        $quote->save();

        $this->getJson('/api/v1/user/religion-content')
            ->assertOk()
            ->assertJsonPath('data.religion_code', 'custom')
            ->assertJsonPath('data.legacy.opening_greeting', 'Salam legacy')
            ->assertJsonPath('data.legacy.whatsapp_message', "WA atas legacy\nWA bawah legacy")
            ->assertJsonPath('data.legacy.quote_text', 'Quote legacy')
            ->assertJsonPath('data.resolved.quote_source', 'Sumber legacy');
    }

    public function test_invalid_religion_code_returns_indonesian_message(): void
    {
        $this->actingAsUser();

        $this->putJson('/api/v1/user/religion-content', [
            'religion_code' => 'invalid-agama',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Kode agama tidak valid.');
    }

    private function actingAsUser(): User
    {
        $role = Role::findOrCreate('user', 'web');

        $user = User::create([
            'name' => 'User Agama',
            'email' => uniqid('agama_', true).'@example.test',
            'password' => bcrypt('secret123'),
            'phone' => '08123456789',
        ]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPublicWeddingUser(string $domain): User
    {
        $user = User::create([
            'name' => 'Public Religion User',
            'email' => $domain.'@example.test',
            'password' => bcrypt('secret123'),
            'phone' => '08123456789',
        ]);

        $package = PaketUndangan::firstOrCreate(
            ['name_paket' => 'Religion Test Package'],
            [
                'jenis_paket' => 'website',
                'price' => 0,
                'masa_aktif' => 30,
                'halaman_buku' => 1,
                'kirim_wa' => true,
                'bebas_pilih_tema' => true,
                'kirim_hadiah' => true,
                'import_data' => true,
            ]
        );

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'status' => 'step3',
            'payment_status' => 'paid',
            'is_trial' => false,
            'kode_pemesanan' => 'INV-'.$user->id,
            'domain_expires_at' => now()->addDays(30),
            'package_price_snapshot' => 0,
            'package_duration_snapshot' => 30,
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
            'name_lengkap_pria' => 'Bima Pratama',
            'name_lengkap_wanita' => 'Sari Ayu',
            'name_panggilan_pria' => 'Bima',
            'name_panggilan_wanita' => 'Sari',
            'status' => 'Sudah Bayar',
            'kd_status' => 'SB',
        ]);

        Acara::create([
            'user_id' => $user->id,
            'nama_acara' => 'Resepsi',
            'tanggal_acara' => '2026-08-01',
            'start_acara' => '10:00',
            'end_acara' => '12:00',
            'alamat' => 'Gedung Bahagia',
            'link_maps' => 'https://maps.example.test',
        ]);

        return $user;
    }
}
