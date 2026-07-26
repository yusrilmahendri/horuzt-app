<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsVerified;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PaymentMethodFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'midtrans.server_key' => 'server-key-test',
            'midtrans.client_key' => 'client-key-test',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withoutMiddleware([
            EnsureAccountIsVerified::class,
            RoleMiddleware::class,
        ]);

        $this->createMinimalSchema();
    }

    public function test_complete_manual_config_returns_manual_and_blocks_snap_token(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin);
        $user = $this->user();
        $invitation = $this->invitationFor($user);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldNotReceive('createTransaction');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'manual')
            ->assertJsonPath('data.manual_payment.bank_name', 'BCA')
            ->assertJsonPath('data.manual_payment.account_number', '1234567890')
            ->assertJsonPath('data.midtrans.enabled', false);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => 100000,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_METHOD_NOT_AVAILABLE')
            ->assertJsonPath('data.payment_method', 'manual');
    }

    public function test_complete_manual_config_allows_manual_invoice_and_ignores_user_payload(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, photo: null);
        $user = $this->user();
        $invitation = $this->invitationFor($user);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/tagihan', [
            'user_id' => $user->id,
            'payment_method' => 'midtrans',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'manual')
            ->assertJsonPath('data.manual_payment.account_photo_url', null);

        $this->assertSame('manual', $invitation->fresh()->payment_method);
    }

    public function test_missing_manual_bank_name_returns_midtrans(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, bankName: '');
        $this->assertPaymentConfigIsMidtrans();
    }

    public function test_missing_manual_account_number_returns_midtrans(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, accountNumber: '');
        $this->assertPaymentConfigIsMidtrans();
    }

    public function test_missing_manual_account_name_returns_midtrans(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, accountName: '');
        $this->assertPaymentConfigIsMidtrans();
    }

    public function test_incomplete_manual_config_allows_midtrans_and_blocks_manual_invoice(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, accountName: '');
        $user = $this->user();
        $invitation = $this->invitationFor($user);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-auto-midtrans');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/tagihan', [
            'user_id' => $user->id,
            'payment_method' => 'manual',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_METHOD_NOT_AVAILABLE')
            ->assertJsonPath('data.payment_method', 'midtrans');

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => 100000,
            'payment_method' => 'manual',
        ])
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token-auto-midtrans');

        $this->assertSame('midtrans', $invitation->fresh()->payment_method);
        $this->assertDatabaseHas('payment_logs', [
            'invitation_id' => $invitation->id,
            'payment_type' => 'midtrans',
        ]);
    }

    public function test_old_admin_active_payment_choice_does_not_override_complete_manual_config(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin);
        $user = $this->user();

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/active-payment-method/3')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'manual');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'manual');
    }

    public function test_existing_transaction_method_is_not_changed_when_manual_config_changes(): void
    {
        $admin = $this->admin();
        $user = $this->user();
        $oldInvitation = $this->invitationFor($user);
        $oldInvitation->update([
            'payment_status' => 'pending',
            'payment_method' => 'manual',
        ]);

        $this->manualAccountFor($admin, accountName: '');

        $this->assertSame('manual', $oldInvitation->fresh()->payment_method);
    }

    public function test_when_manual_and_midtrans_are_unavailable_returns_clear_error(): void
    {
        config([
            'midtrans.server_key' => null,
            'midtrans.client_key' => null,
        ]);

        $user = $this->user();
        $invitation = $this->invitationFor($user);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'midtrans')
            ->assertJsonPath('data.midtrans.configured', false);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => 100000,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_METHOD_UNAVAILABLE')
            ->assertJsonPath('message', 'Metode pembayaran belum tersedia. Silakan hubungi admin.');
    }

    private function assertPaymentConfigIsMidtrans(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'midtrans')
            ->assertJsonPath('data.midtrans.enabled', true)
            ->assertJsonPath('data.manual_payment', null);
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name' => 'Admin Payment',
            'email' => 'admin-payment-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function manualAccountFor(User $admin, string $bankName = 'BCA', string $accountNumber = '1234567890', string $accountName = 'Sena Digital', ?string $photo = 'rekening/bca.png'): void
    {
        DB::table('rekenings')->insert([
            'user_id' => $admin->id,
            'kode_bank' => 'BCA',
            'email' => $admin->email,
            'nomor_rekening' => $accountNumber,
            'nama_bank' => $bankName,
            'nama_pemilik' => $accountName,
            'methode_pembayaran' => 'Manual',
            'id_methode_pembayaran' => '1',
            'photo_rek' => $photo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'User Payment',
            'email' => 'user-payment-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
            'phone' => '08123456789',
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function invitationFor(User $user): Invitation
    {
        $package = PaketUndangan::create([
            'code' => 'ruby',
            'jenis_paket' => 'Paket Ruby',
            'name_paket' => 'Paket Ruby',
            'price' => 100000,
            'masa_aktif' => 30,
        ]);

        return Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => '#PAY-'.$user->id.'-'.str()->random(4),
            'status' => 'step1',
            'payment_status' => 'unpaid',
            'package_price_snapshot' => 100000,
            'package_duration_snapshot' => 30,
            'package_features_snapshot' => [
                'code' => 'ruby',
                'name_paket' => 'Paket Ruby',
            ],
        ]);
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
            $table->timestamps();
        });

        Schema::create('metode_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('metode_transactions')->insert([
            ['id' => 1, 'name' => 'Manual', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Midtrans', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::create('active_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('metode_transaction_id')->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('rekenings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kode_bank');
            $table->string('email');
            $table->string('nomor_rekening');
            $table->string('nama_bank');
            $table->string('nama_pemilik');
            $table->string('methode_pembayaran');
            $table->string('id_methode_pembayaran');
            $table->string('photo_rek')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->integer('trial_masa_aktif')->nullable();
            $table->timestamps();
        });

        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->nullable();
            $table->string('kd_status')->nullable();
            $table->timestamps();
        });

        Schema::create('paket_undangans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('jenis_paket')->nullable();
            $table->string('name_paket')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('masa_aktif')->default(30);
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_undangan_id')->nullable();
            $table->string('kode_pemesanan')->nullable();
            $table->string('status')->nullable();
            $table->string('order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('payment_status')->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->boolean('is_trial')->default(false);
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->decimal('package_price_snapshot', 10, 2)->nullable();
            $table->integer('package_duration_snapshot')->nullable();
            $table->json('package_features_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->string('order_id')->nullable();
            $table->string('midtrans_transaction_id')->nullable();
            $table->string('event_type')->default('token_request');
            $table->string('transaction_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('signature_key')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('error_message')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('permissions', function (Blueprint $table) {
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
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
    }
}
