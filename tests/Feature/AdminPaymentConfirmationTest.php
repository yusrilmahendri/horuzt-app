<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\Mempelai;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPaymentConfirmationTest extends TestCase
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

    public function test_kode_invoice_valid_berhasil(): void
    {
        $admin = $this->admin();
        $user = $this->userWithPendingInvoice('#4101884938');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $user->id,
            'kode_pemesanan' => '#4101884938',
        ])
            ->assertOk()
            ->assertJsonPath('invitation.payment_status', 'paid')
            ->assertJsonPath('invitation.status', 'completed')
            ->assertJsonPath('account_status', 'active');
    }

    public function test_kode_dengan_hash_berhasil_jika_database_tanpa_hash(): void
    {
        $admin = $this->admin();
        $user = $this->userWithPendingInvoice('4101884938');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $user->id,
            'kode_pemesanan' => '#4101884938',
        ])
            ->assertOk()
            ->assertJsonPath('invitation.payment_status', 'paid')
            ->assertJsonPath('account_status', 'active');
    }

    public function test_status_belum_selesai_bisa_dikonfirmasi(): void
    {
        $admin = $this->admin();
        $user = $this->userWithPendingInvoice('#BELUM-SELESAI', 'belum selesai');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $user->id,
            'kode_pemesanan' => 'BELUM-SELESAI',
        ])
            ->assertOk()
            ->assertJsonPath('invitation.payment_status', 'paid')
            ->assertJsonPath('account_status', 'active');
    }

    public function test_kode_salah_error_jelas(): void
    {
        $admin = $this->admin();
        $user = $this->userWithPendingInvoice('#4101884938');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $user->id,
            'kode_pemesanan' => '#SALAH',
        ])
            ->assertNotFound()
            ->assertJsonPath('message', 'Kode pemesanan tidak ditemukan untuk pengguna ini.');
    }

    public function test_invoice_bukan_milik_user_ditolak(): void
    {
        $admin = $this->admin();
        $owner = $this->userWithPendingInvoice('#OWNER');
        $other = $this->userWithPendingInvoice('#OTHER');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $other->id,
            'kode_pemesanan' => '#OWNER',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kode pemesanan tidak sesuai dengan pengguna.');

        $this->assertSame('pending', Invitation::where('user_id', $owner->id)->firstOrFail()->payment_status);
    }

    public function test_setelah_confirm_user_active(): void
    {
        $admin = $this->admin();
        $user = $this->userWithPendingInvoice('#ACTIVE-ME');

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/update/status-bayar', [
            'user_id' => $user->id,
            'kode_pemesanan' => '#ACTIVE-ME',
        ])->assertOk();

        Sanctum::actingAs($user);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.is_payment_confirmed', true);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function userWithPendingInvoice(string $kodePemesanan, string $invoiceStatus = 'step1'): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
        $user->assignRole('user');

        $package = PaketUndangan::first() ?? PaketUndangan::create([
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
            'kode_pemesanan' => $kodePemesanan,
            'paket_undangan_id' => $package->id,
            'status' => $invoiceStatus,
            'payment_status' => 'pending',
            'is_trial' => false,
            'domain_expires_at' => null,
            'payment_confirmed_at' => null,
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'code' => $package->code,
                'name_paket' => $package->name_paket,
            ],
        ]);

        Mempelai::create(['user_id' => $user->id, 'status' => 'Menunggu Konfirmasi', 'kd_status' => 'MK']);

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
            $table->string('kode_pemesanan')->nullable();
            $table->unsignedBigInteger('paket_undangan_id');
            $table->string('order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
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
            $table->timestamps();
        });
    }
}
