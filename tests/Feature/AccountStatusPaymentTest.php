<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountStatusPaymentTest extends TestCase
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
    }

    public function test_user_unverified_menghasilkan_unverified(): void
    {
        $user = $this->makeUser(false, 'paid', now()->addDays(10), now());

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'unverified')
            ->assertJsonPath('data.is_verified', false);
    }

    public function test_user_verified_tapi_belum_punya_invoice_menghasilkan_pilihan_pembayaran(): void
    {
        $user = $this->makeUserWithoutInvoice(true);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'verified_no_invoice')
            ->assertJsonPath('data.subscription_status', 'verified_no_invoice')
            ->assertJsonPath('data.payment_requirement', 'initial_payment')
            ->assertJsonPath('data.pending_invoice', null)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.payment_status', null)
            ->assertJsonPath('data.has_invoice', false)
            ->assertJsonPath('data.has_pending_invoice', false)
            ->assertJsonPath('data.invoice_id', null)
            ->assertJsonPath('data.invoice_code', null)
            ->assertJsonPath('data.is_payment_confirmed', false)
            ->assertJsonPath('data.next_step', 'select-package-payment-method')
            ->assertJsonPath('data.redirect_url', '/pilih-paket');
    }

    public function test_user_verified_dengan_invoice_pending_menghasilkan_pending_payment(): void
    {
        $user = $this->makeUser(true, 'pending', now()->addDays(10), null, '#INV-PENDING');

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'pending_payment')
            ->assertJsonPath('data.subscription_status', 'pending_payment')
            ->assertJsonPath('data.payment_requirement', 'initial_payment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.has_invoice', true)
            ->assertJsonPath('data.has_pending_invoice', true)
            ->assertJsonPath('data.invoice_code', '#INV-PENDING')
            ->assertJsonPath('data.pending_invoice.invoice_code', '#INV-PENDING')
            ->assertJsonPath('data.pending_invoice.payment_status', 'pending')
            ->assertJsonPath('data.pending_invoice.package.code', 'ruby')
            ->assertJsonPath('data.kode_pemesanan', '#INV-PENDING')
            ->assertJsonPath('data.package_name', 'Paket Ruby')
            ->assertJsonPath('data.package_code', 'ruby')
            ->assertJsonPath('data.feature_access.input_undangan', false)
            ->assertJsonPath('data.is_payment_confirmed', false);
    }

    public function test_user_paid_aktif_menghasilkan_active(): void
    {
        $user = $this->makeUser(true, 'paid', now()->addDays(10), now());

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.subscription_status', 'active')
            ->assertJsonPath('data.payment_requirement', null)
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.has_invoice', true)
            ->assertJsonPath('data.has_pending_invoice', false)
            ->assertJsonPath('data.is_payment_confirmed', true)
            ->assertJsonPath('data.feature_access.input_undangan', true)
            ->assertJsonPath('data.active_until_formatted', now()->addDays(10)->format('d/m/Y'))
            ->assertJsonPath('data.expired_at_formatted', now()->addDays(10)->format('d/m/Y'))
            ->assertJsonPath('data.tanggal_expired_formatted', now()->addDays(10)->format('d/m/Y'));
    }

    public function test_user_expired_menghasilkan_expired(): void
    {
        $user = $this->makeUser(true, 'paid', now()->subDay(), now()->subDays(2));

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'expired')
            ->assertJsonPath('data.is_expired', true);
    }

    public function test_user_active_dengan_pending_upgrade_tetap_active_dan_pending_invoice_terlihat(): void
    {
        $user = $this->makeUser(true, 'paid', now()->addDays(10), now(), '#INV-ACTIVE');
        $diamond = PaketUndangan::create([
            'code' => 'diamond',
            'jenis_paket' => 'Paket Diamond',
            'name_paket' => 'Paket Diamond',
            'price' => 300000,
            'masa_aktif' => 30,
        ]);

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $diamond->id,
            'kode_pemesanan' => 'UPG-ACTIVE-PENDING',
            'status' => 'step1',
            'payment_status' => 'pending',
            'package_price_snapshot' => $diamond->price,
            'package_duration_snapshot' => $diamond->masa_aktif,
            'package_features_snapshot' => [
                'invoice_type' => 'package_upgrade',
                'change_type' => 'upgrade',
                'code' => 'diamond',
                'name_paket' => 'Paket Diamond',
            ],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.has_pending_invoice', true)
            ->assertJsonPath('data.payment_requirement', 'upgrade_payment')
            ->assertJsonPath('data.invoice_code', '#INV-ACTIVE')
            ->assertJsonPath('data.pending_invoice.invoice_code', 'UPG-ACTIVE-PENDING')
            ->assertJsonPath('data.pending_invoice.invoice_type', 'package_upgrade')
            ->assertJsonPath('data.pending_invoice.change_type', 'upgrade')
            ->assertJsonPath('data.pending_invoice.package.code', 'diamond');
    }

    public function test_user_expired_dengan_pending_renewal_tetap_expired_dan_bisa_resume_invoice(): void
    {
        $user = $this->makeUser(true, 'paid', now()->subDay(), now()->subDays(40), '#INV-EXPIRED');
        $package = PaketUndangan::where('code', 'ruby')->firstOrFail();

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => 'UPG-RENEW-PENDING',
            'status' => 'step1',
            'payment_status' => 'pending',
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'invoice_type' => 'package_upgrade',
                'change_type' => 'renew',
                'code' => 'ruby',
                'name_paket' => 'Paket Ruby',
            ],
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'expired')
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.has_pending_invoice', true)
            ->assertJsonPath('data.payment_requirement', 'renewal_payment')
            ->assertJsonPath('data.invoice_code', '#INV-EXPIRED')
            ->assertJsonPath('data.pending_invoice.invoice_code', 'UPG-RENEW-PENDING')
            ->assertJsonPath('data.pending_invoice.change_type', 'renew');
    }

    public function test_user_pending_payment_tidak_bisa_akses_fitur_input(): void
    {
        $user = $this->makeUser(true, 'pending', now()->addDays(10));

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/update-mempelai', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIRMED');
    }

    public function test_user_verified_tanpa_invoice_tidak_bisa_akses_fitur_input(): void
    {
        $user = $this->makeUserWithoutInvoice(true);
        Mempelai::create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/update-mempelai', [
            'name_lengkap_pria' => 'Budi',
            'name_lengkap_wanita' => 'Sari',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'PAYMENT_NOT_CONFIRMED');
    }

    public function test_user_expired_tidak_bisa_akses_fitur_input(): void
    {
        $user = $this->makeUser(true, 'paid', now()->subDay(), now()->subDays(2));

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/update-mempelai', [])
            ->assertForbidden()
            ->assertJsonPath('code', 'ACCOUNT_EXPIRED');
    }

    public function test_user_active_bisa_akses_fitur_input(): void
    {
        $user = $this->makeUser(true, 'paid', now()->addDays(10), now());
        Mempelai::create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/update-mempelai', [
            'name_lengkap_pria' => 'Budi',
            'name_lengkap_wanita' => 'Sari',
        ])->assertOk();
    }

    private function makeUser(
        bool $verified,
        string $paymentStatus,
        $activeUntil,
        $paymentConfirmedAt = null,
        ?string $invoiceCode = null
    ): User {
        $user = $this->makeUserWithoutInvoice($verified);

        $package = PaketUndangan::create([
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

        Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => $invoiceCode,
            'status' => 'step1',
            'payment_status' => $paymentStatus,
            'domain_expires_at' => $activeUntil,
            'payment_confirmed_at' => $paymentConfirmedAt,
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'code' => $package->code,
                'name_paket' => $package->name_paket,
            ],
        ]);

        return $user;
    }

    private function makeUserWithoutInvoice(bool $verified): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => $verified ? now() : null,
            'verification_channel' => 'email',
        ]);
        $user->assignRole('user');

        return $user;
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
            $table->string('profile_photo')->nullable();
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

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_undangan_id');
            $table->string('kode_pemesanan')->nullable();
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

        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->nullable();
            $table->string('kd_status')->nullable();
            $table->string('cover_photo')->nullable();
            $table->string('photo_pria')->nullable();
            $table->string('photo_wanita')->nullable();
            $table->string('urutan_mempelai')->nullable();
            $table->string('name_lengkap_pria')->nullable();
            $table->string('name_lengkap_wanita')->nullable();
            $table->string('name_panggilan_pria')->nullable();
            $table->string('name_panggilan_wanita')->nullable();
            $table->string('ayah_pria')->nullable();
            $table->string('ayah_wanita')->nullable();
            $table->string('ibu_pria')->nullable();
            $table->string('ibu_wanita')->nullable();
            $table->timestamps();
        });
    }
}
