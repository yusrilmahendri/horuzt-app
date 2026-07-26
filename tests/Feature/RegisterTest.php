<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RegisterTest extends TestCase
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

        $this->createMinimalSchema();
    }

    public function test_register_requires_password_confirmation(): void
    {
        $payload = $this->validPayload();
        unset($payload['password_confirmation']);

        $this->postJson('/api/v1/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('password_confirmation')
            ->assertJsonPath('errors.password_confirmation.0', 'Ulangi password wajib diisi.');
    }

    public function test_register_rejects_mismatched_password_confirmation(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload([
            'password_confirmation' => 'password-salah',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password')
            ->assertJsonPath('errors.password.0', 'Ulangi password tidak sama dengan password.');
    }

    public function test_register_succeeds_with_matching_confirmation_and_keeps_verification_flow(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Registrasi berhasil. Silakan verifikasi akun Anda.')
            ->assertJsonPath('data.user.email', 'register@example.test')
            ->assertJsonPath('data.user.email_verified_at', null)
            ->assertJsonStructure([
                'data' => [
                    'access_token',
                    'token_type',
                    'user',
                ],
            ])
            ->assertJsonMissing(['password_confirmation' => 'password123']);

        $user = User::where('email', 'register@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertNotSame('password123', $user->password);
        $this->assertSame('email', $user->verification_channel);
        $this->assertFalse($user->isAccountVerified());
        $this->assertFalse(Schema::hasColumn('users', 'password_confirmation'));
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('roles', [
            'name' => 'user',
            'guard_name' => 'web',
        ]);
    }

    public function test_existing_password_length_validation_still_runs(): void
    {
        $this->postJson('/api/v1/register', $this->validPayload([
            'password' => 'short',
            'password_confirmation' => 'short',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Register User',
            'email' => 'register@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'verification_channel' => 'email',
        ], $overrides);
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

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
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
