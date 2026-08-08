<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsVerified;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Services\MidtransService;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserPackageUpgradeTest extends TestCase
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

    public function test_user_packages_returns_database_packages_with_upgrade_flags(): void
    {
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $starter);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/packages')
            ->assertOk()
            ->assertJsonPath('0.name', 'Starter Custom')
            ->assertJsonPath('0.is_current', true)
            ->assertJsonPath('0.action', 'current')
            ->assertJsonPath('0.rank', 1)
            ->assertJsonPath('0.can_upgrade', false)
            ->assertJsonPath('1.name', 'Pro Custom')
            ->assertJsonPath('1.action', 'upgrade')
            ->assertJsonPath('1.can_upgrade', true)
            ->assertJsonPath('1.features.halaman_buku', 100);
    }

    public function test_user_can_create_manual_package_upgrade_invoice(): void
    {
        [$starter, $pro] = $this->packages();
        $admin = $this->admin();
        $this->manualAccountFor($admin);
        $user = $this->user();
        $this->paidInvitation($user, $starter);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('package_before.id', $starter->id)
            ->assertJsonPath('package_after.id', $pro->id)
            ->assertJsonPath('payment_method', 'manual')
            ->assertJsonPath('payment_status', 'pending')
            ->assertJsonPath('original_price', 150000)
            ->assertJsonPath('discount_percentage', 40)
            ->assertJsonPath('discount_amount', 60000)
            ->assertJsonPath('amount', 90000);

        $this->assertDatabaseHas('invitations', [
            'user_id' => $user->id,
            'paket_undangan_id' => $pro->id,
            'payment_method' => 'manual',
            'payment_status' => 'pending',
        ]);

        $invoice = Invitation::where('user_id', $user->id)
            ->where('payment_status', 'pending')
            ->firstOrFail();
        $this->assertSame('90000.00', $invoice->package_price_snapshot);

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.pending_invoice.id', $invoice->id)
            ->assertJsonPath('data.pending_invoice.payment_method', 'manual')
            ->assertJsonPath('data.pending_invoice.provider', 'manual')
            ->assertJsonPath('data.pending_invoice.resume.type', 'manual_payment')
            ->assertJsonPath('data.pending_invoice.resume.available', true)
            ->assertJsonPath('data.pending_invoice.resume.endpoint', '/api/v1/user/payment-config')
            ->assertJsonPath('data.pending_invoice.manual_payment.account_number', '1234567890');
    }

    public function test_active_user_cannot_downgrade_and_same_package_is_current(): void
    {
        [$starter, $pro] = $this->packages();
        $admin = $this->admin();
        $this->manualAccountFor($admin);
        $user = $this->user();
        $this->paidInvitation($user, $pro);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $starter->id,
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'PACKAGE_DOWNGRADE_NOT_ALLOWED')
            ->assertJsonPath('message', 'Downgrade paket tidak tersedia.')
            ->assertJsonPath('package_before.id', $pro->id)
            ->assertJsonPath('package_after.id', $starter->id)
            ->assertJsonPath('action', 'downgrade');

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertStatus(422)
            ->assertJsonPath('code', 'PACKAGE_UPGRADE_NOT_REQUIRED')
            ->assertJsonPath('message', 'Paket sedang aktif.');
    }

    public function test_expired_subscription_can_select_lower_same_and_higher_packages(): void
    {
        [$starter, $pro] = $this->packages();
        $enterprise = PaketUndangan::create([
            'code' => 'enterprise-custom',
            'jenis_paket' => 'Enterprise Custom',
            'name_paket' => 'Enterprise Custom',
            'price' => 250000,
            'masa_aktif' => 90,
        ]);
        $user = $this->user();
        $this->paidInvitation($user, $pro, expired: true);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/user/packages')
            ->assertOk()
            ->assertJsonPath('0.id', $starter->id)
            ->assertJsonPath('0.subscription_status', 'expired')
            ->assertJsonPath('0.can_select', true)
            ->assertJsonPath('0.action', 'subscribe')
            ->assertJsonPath('1.id', $pro->id)
            ->assertJsonPath('1.is_current', false)
            ->assertJsonPath('1.is_last_package', true)
            ->assertJsonPath('1.can_select', true)
            ->assertJsonPath('1.action', 'renew')
            ->assertJsonPath('2.id', $enterprise->id)
            ->assertJsonPath('2.can_select', true);
    }

    public function test_expired_subscription_can_create_lower_package_invoice_without_downgrade_error(): void
    {
        [$starter, $pro] = $this->packages();
        $admin = $this->admin();
        $this->manualAccountFor($admin);
        $user = $this->user();
        $this->paidInvitation($user, $pro, expired: true);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $starter->id,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('package_before.id', $pro->id)
            ->assertJsonPath('package_after.id', $starter->id)
            ->assertJsonPath('subscription_status', 'expired')
            ->assertJsonPath('action', 'subscribe');
    }

    public function test_manual_payment_config_response_contains_checkout_fields(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin);

        Sanctum::actingAs($this->user());

        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'manual')
            ->assertJsonPath('data.manual_payment.type', 'manual')
            ->assertJsonPath('data.manual_payment.bank_name', 'BCA')
            ->assertJsonPath('data.manual_payment.account_number', '1234567890')
            ->assertJsonPath('data.manual_payment.account_holder', 'Sena Digital')
            ->assertJsonPath('data.manual_payment.is_active', true);
    }

    public function test_manual_payment_config_is_hidden_when_incomplete(): void
    {
        $admin = $this->admin();
        $this->manualAccountFor($admin, accountNumber: '');

        Sanctum::actingAs($this->user());

        $this->getJson('/api/v1/user/payment-config')
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'midtrans')
            ->assertJsonPath('data.manual_payment', null);
    }

    public function test_midtrans_settlement_activates_subscription_and_duplicate_webhook_is_idempotent(): void
    {
        Notification::fake();
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $starter);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-upgrade');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertCreated()
            ->assertJsonPath('payment_method', 'midtrans')
            ->assertJsonPath('snap_token', 'snap-token-upgrade')
            ->assertJsonPath('original_price', 150000)
            ->assertJsonPath('discount_percentage', 40)
            ->assertJsonPath('discount_amount', 60000)
            ->assertJsonPath('amount', 90000);

        $orderId = $response->json('order_id');
        $invoice = Invitation::where('order_id', $orderId)->firstOrFail();
        $payload = $this->midtransWebhookPayload($invoice, 'settlement');

        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertOk();
        $firstExpiry = $invoice->fresh()->domain_expires_at?->toDateTimeString();
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertDatabaseHas('package_upgrade_histories', [
            'invitation_id' => $invoice->id,
            'package_after_id' => $pro->id,
            'payment_status' => 'paid',
        ]);

        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertOk();
        $this->assertSame($firstExpiry, $invoice->fresh()->domain_expires_at?->toDateTimeString());
        $this->assertSame(1, DB::table('package_upgrade_histories')->where('invitation_id', $invoice->id)->count());
    }

    public function test_midtrans_expire_marks_transaction_failed_without_activation(): void
    {
        Notification::fake();
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $starter);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-expire');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertCreated()->json('order_id');

        $invoice = Invitation::where('order_id', $orderId)->firstOrFail();
        $this->postJson('/api/v1/midtrans/webhook', $this->midtransWebhookPayload($invoice, 'expire'))
            ->assertOk();

        $this->assertSame('failed', $invoice->fresh()->payment_status);
        $this->assertNull($invoice->fresh()->payment_confirmed_at);
        $this->assertDatabaseMissing('package_upgrade_histories', [
            'invitation_id' => $invoice->id,
        ]);
    }

    public function test_midtrans_frontend_callback_does_not_activate_subscription(): void
    {
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $starter);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-callback');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $orderId = $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertCreated()->json('order_id');

        $this->postJson('/api/v1/midtrans/confirm-success', [
            'order_id' => $orderId,
            'transaction_id' => 'frontend-only',
            'gross_amount' => 150000,
        ])->assertOk()
            ->assertJsonPath('payment_status', 'pending');

        $invoice = Invitation::where('order_id', $orderId)->firstOrFail();
        $this->assertSame('pending', $invoice->payment_status);
        $this->assertNull($invoice->payment_confirmed_at);
    }

    public function test_expired_subscription_upgrade_pending_stays_expired_until_midtrans_settlement(): void
    {
        Notification::fake();
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $starter, expired: true);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-expired-upgrade');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertCreated()
            ->assertJsonPath('subscription_status', 'expired')
            ->assertJsonPath('action', 'subscribe')
            ->assertJsonPath('payment_status', 'pending');

        $orderId = $response->json('order_id');
        $invoice = Invitation::where('order_id', $orderId)->firstOrFail();

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'expired')
            ->assertJsonPath('data.has_pending_invoice', true)
            ->assertJsonPath('data.payment_requirement', 'upgrade_payment')
            ->assertJsonPath('data.pending_invoice.id', $invoice->id)
            ->assertJsonPath('data.pending_invoice.package.code', 'pro-custom')
            ->assertJsonPath('data.pending_invoice.payment_method', 'midtrans')
            ->assertJsonPath('data.pending_invoice.provider', 'midtrans')
            ->assertJsonPath('data.pending_invoice.midtrans.order_id', $orderId)
            ->assertJsonPath('data.pending_invoice.midtrans.snap_token', 'snap-token-expired-upgrade')
            ->assertJsonPath('data.pending_invoice.resume.type', 'midtrans_snap')
            ->assertJsonPath('data.pending_invoice.resume.available', true)
            ->assertJsonPath('data.pending_invoice.resume.reuses_existing_order', true)
            ->assertJsonPath('data.pending_invoice.resume.payload.invitation_id', $invoice->id);

        $this->postJson('/api/v1/midtrans/webhook', $this->midtransWebhookPayload($invoice, 'settlement'))
            ->assertOk();

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.package_code', 'pro-custom')
            ->assertJsonPath('data.has_pending_invoice', false)
            ->assertJsonPath('data.payment_requirement', null);
    }

    public function test_expired_subscription_renewal_resumes_pending_invoice_and_activates_after_payment(): void
    {
        Notification::fake();
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $pro, expired: true);

        $midtrans = \Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-renewal');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertCreated()
            ->assertJsonPath('subscription_status', 'expired')
            ->assertJsonPath('action', 'renew')
            ->assertJsonPath('snap_token', 'snap-token-renewal');

        $orderId = $first->json('order_id');

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertOk()
            ->assertJsonPath('order_id', $orderId)
            ->assertJsonPath('snap_token', 'snap-token-renewal')
            ->assertJsonPath('reused', true)
            ->assertJsonPath('action', 'renew');

        $invoice = Invitation::where('order_id', $orderId)->firstOrFail();

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'expired')
            ->assertJsonPath('data.payment_requirement', 'renewal_payment')
            ->assertJsonPath('data.pending_invoice.change_type', 'renew');

        $this->postJson('/api/v1/midtrans/webhook', $this->midtransWebhookPayload($invoice, 'settlement'))
            ->assertOk();

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'active')
            ->assertJsonPath('data.package_code', 'pro-custom')
            ->assertJsonPath('data.has_pending_invoice', false);

        $this->assertSame(2, Invitation::where('user_id', $user->id)->count());
    }

    private function packages(): array
    {
        return [
            PaketUndangan::create([
                'code' => 'starter-custom',
                'jenis_paket' => 'Starter Custom',
                'name_paket' => 'Starter Custom',
                'price' => 50000,
                'masa_aktif' => 30,
                'halaman_buku' => 20,
            ]),
            PaketUndangan::create([
                'code' => 'pro-custom',
                'jenis_paket' => 'Pro Custom',
                'name_paket' => 'Pro Custom',
                'price' => 150000,
                'masa_aktif' => 60,
                'halaman_buku' => 100,
                'kirim_wa' => true,
            ]),
        ];
    }

    private function user(): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Upgrade User',
            'email' => 'upgrade-user-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
            'verification_channel' => 'email',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('user');

        return $user;
    }

    private function admin(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::create([
            'name' => 'Upgrade Admin',
            'email' => 'upgrade-admin-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        return $admin;
    }

    private function paidInvitation(User $user, PaketUndangan $package, bool $expired = false): Invitation
    {
        return Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => '#PKG-'.$user->id.'-'.$package->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'manual',
            'domain_expires_at' => $expired ? now()->subDay() : now()->addDays(30),
            'payment_confirmed_at' => now()->subDays($expired ? 40 : 0),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'package_id' => $package->id,
                'name_paket' => $package->name_paket,
            ],
        ]);
    }

    private function manualAccountFor(User $admin, string $accountNumber = '1234567890'): void
    {
        DB::table('rekenings')->insert([
            'user_id' => $admin->id,
            'kode_bank' => 'BCA',
            'email' => $admin->email,
            'nomor_rekening' => $accountNumber,
            'nama_bank' => 'BCA',
            'nama_pemilik' => 'Sena Digital',
            'methode_pembayaran' => 'Manual',
            'id_methode_pembayaran' => '1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function midtransWebhookPayload(Invitation $invoice, string $transactionStatus): array
    {
        $grossAmount = number_format((float) $invoice->package_price_snapshot, 2, '.', '');
        $statusCode = $transactionStatus === 'settlement' ? '200' : '201';

        return [
            'order_id' => $invoice->order_id,
            'transaction_status' => $transactionStatus,
            'transaction_id' => 'trx-'.$transactionStatus.'-'.$invoice->id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'payment_type' => 'bank_transfer',
            'signature_key' => hash('sha512', $invoice->order_id.$statusCode.$grossAmount.config('midtrans.server_key')),
        ];
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
            $table->string('code')->nullable();
            $table->string('jenis_paket')->nullable();
            $table->string('name_paket')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('masa_aktif')->default(30);
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

        Schema::create('midtrans_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('server_key')->nullable();
            $table->string('client_key')->nullable();
            $table->string('metode_production')->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('mempelais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->nullable();
            $table->string('kd_status')->nullable();
            $table->timestamps();
        });

        Schema::create('package_upgrade_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('invitation_id')->nullable();
            $table->unsignedBigInteger('package_before_id')->nullable();
            $table->unsignedBigInteger('package_after_id');
            $table->string('payment_method', 20);
            $table->string('payment_status', 30);
            $table->decimal('amount', 15, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
}
