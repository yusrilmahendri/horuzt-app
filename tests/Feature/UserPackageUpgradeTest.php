<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsVerified;
use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;
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
            ->assertJsonPath('0.can_upgrade', false)
            ->assertJsonPath('1.name', 'Pro Custom')
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
            ->assertJsonPath('payment_status', 'pending');

        $this->assertDatabaseHas('invitations', [
            'user_id' => $user->id,
            'paket_undangan_id' => $pro->id,
            'payment_method' => 'manual',
            'payment_status' => 'pending',
        ]);
    }

    public function test_user_cannot_downgrade_or_upgrade_to_same_package(): void
    {
        [$starter, $pro] = $this->packages();
        $user = $this->user();
        $this->paidInvitation($user, $pro);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $starter->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Downgrade paket tidak diperbolehkan.');

        $this->postJson('/api/v1/user/package-upgrade', [
            'package_id' => $pro->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Tidak dapat upgrade ke paket yang sama.');
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
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
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

    private function paidInvitation(User $user, PaketUndangan $package): Invitation
    {
        return Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => '#PKG-'.$user->id.'-'.$package->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'manual',
            'domain_expires_at' => now()->addDays(30),
            'payment_confirmed_at' => now(),
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'package_id' => $package->id,
                'name_paket' => $package->name_paket,
            ],
        ]);
    }

    private function manualAccountFor(User $admin): void
    {
        DB::table('rekenings')->insert([
            'user_id' => $admin->id,
            'kode_bank' => 'BCA',
            'email' => $admin->email,
            'nomor_rekening' => '1234567890',
            'nama_bank' => 'BCA',
            'nama_pemilik' => 'Sena Digital',
            'methode_pembayaran' => 'Manual',
            'id_methode_pembayaran' => '1',
            'created_at' => now(),
            'updated_at' => now(),
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
            $table->string('event_type')->default('token_request');
            $table->string('transaction_status')->nullable();
            $table->string('payment_type')->nullable();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->text('request_payload')->nullable();
            $table->text('response_payload')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
}
