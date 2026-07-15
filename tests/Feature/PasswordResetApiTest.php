<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
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

    public function test_token_valid_berhasil_reset_password(): void
    {
        $user = $this->user('valid-reset@example.test', 'old-password');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_forgot_password_mengirim_satu_email_dengan_token_hash_kompatibel(): void
    {
        Notification::fake();
        config(['verification.frontend_url' => 'https://www.sena-digital.com']);
        $user = $this->user('forgot-reset@example.test', 'old-password');

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
            'channel' => 'email',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Link reset kata sandi telah dikirim ke email Anda.');

        $record = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($record);
        Notification::assertSentTo($user, CustomResetPasswordNotification::class, function ($notification) use ($record, $user) {
            $mail = $notification->toMail($user);

            return Hash::check($notification->token, $record->token)
                && str_contains($mail->viewData['resetUrl'], 'https://www.sena-digital.com/reset-password?token=')
                && str_contains($mail->viewData['resetUrl'], 'email='.urlencode($user->email));
        });
    }

    public function test_forgot_password_whatsapp_ditolak_sementara(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'identifier' => '08123456789',
            'channel' => 'whatsapp',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Verifikasi WhatsApp sementara tidak tersedia.');
    }

    public function test_token_salah_ditolak(): void
    {
        $user = $this->user('wrong-reset@example.test', 'old-password');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'RESET_TOKEN_INVALID')
            ->assertJsonPath('message', 'Token reset tidak valid.');
    }

    public function test_token_expired_ditolak_dengan_pesan_jelas(): void
    {
        $user = $this->user('expired-reset@example.test', 'old-password');
        $token = Password::broker()->createToken($user);
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(120)]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'RESET_TOKEN_EXPIRED')
            ->assertJsonPath('message', 'Token reset sudah kedaluwarsa.');
    }


    public function test_password_confirmation_beda_ditolak(): void
    {
        $user = $this->user('confirm-reset@example.test', 'old-password');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_setelah_reset_user_bisa_login_dengan_password_baru(): void
    {
        $user = $this->user('login-reset@example.test', 'old-password');
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'role']);
    }

    private function user(string $email, string $password): User
    {
        return User::create([
            'name' => 'Reset User',
            'email' => $email,
            'password' => Hash::make($password),
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
            $table->string('remember_token')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('account_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('channel', 20);
            $table->string('purpose', 40);
            $table->string('token_hash');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
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

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
    }
}
