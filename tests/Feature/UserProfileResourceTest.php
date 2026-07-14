<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserProfileResourceTest extends TestCase
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
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createMinimalSchema();
    }

    public function test_user_profile_tanpa_relasi_optional_tetap_200(): void
    {
        $user = $this->actingUser();

        $this->getJson('/api/v1/user-profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.account_status', 'onboarding')
            ->assertJsonPath('data.invitations', []);
    }

    public function test_user_profile_dengan_invoice_tetap_mengembalikan_data(): void
    {
        $user = $this->actingUser('with-invoice@example.test');
        $package = PaketUndangan::create([
            'code' => 'ruby',
            'jenis_paket' => 'Paket Ruby',
            'name_paket' => 'Paket Ruby',
            'price' => 100000,
            'masa_aktif' => 30,
        ]);

        $invoice = Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => '#PROFILE-1',
            'status' => 'step1',
            'payment_status' => 'pending',
            'package_features_snapshot' => [
                'code' => 'ruby',
                'name_paket' => 'Paket Ruby',
            ],
        ]);

        $this->getJson('/api/v1/user-profile')
            ->assertOk()
            ->assertJsonPath('data.account_status', 'pending_payment')
            ->assertJsonPath('data.invoice_code', '#PROFILE-1')
            ->assertJsonPath('data.invitations.0.id', $invoice->id)
            ->assertJsonPath('data.invitations.0.paket_undangan.id', $package->id);
    }

    private function actingUser(string $email = 'profile-empty@example.test'): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Profile User',
            'email' => $email,
            'password' => bcrypt('secret123'),
        ]);
        $user->forceFill([
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ])->save();
        $user->assignRole('user');

        Sanctum::actingAs($user);

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
            $table->string('code', 32)->nullable();
            $table->string('jenis_paket');
            $table->string('name_paket');
            $table->decimal('price', 10, 2);
            $table->integer('masa_aktif');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('paket_undangan_id')->nullable();
            $table->string('kode_pemesanan')->nullable();
            $table->string('status')->default('step1');
            $table->string('payment_status')->default('pending');
            $table->timestamp('domain_expires_at')->nullable();
            $table->timestamp('payment_confirmed_at')->nullable();
            $table->decimal('package_price_snapshot', 10, 2)->nullable();
            $table->integer('package_duration_snapshot')->nullable();
            $table->json('package_features_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('domain')->nullable();
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
