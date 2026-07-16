<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\AccountVerificationToken;
use App\Models\PaketUndangan;
use App\Models\User;
use App\Notifications\CustomResetPasswordNotification;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_an_unverified_account(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Pengguna Baru',
            'email' => 'new@example.test',
            'password' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email_verified_at', null)
            ->assertJsonPath('data.user.name', 'Pengguna Baru');
        $user = User::whereEmail('new@example.test')->firstOrFail();
        $this->assertSame('Pengguna Baru', $user->name);
        $this->assertFalse($user->isAccountVerified());
    }

    public function test_registration_requires_name(): void
    {
        $this->postJson('/api/v1/register', [
            'email' => 'noname@example.test',
            'password' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name')
            ->assertJsonPath('errors.name.0', 'Nama pengguna wajib diisi.');
    }

    public function test_registered_name_is_returned_by_profile_and_account_status(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Nama Pengguna',
            'email' => 'profile-name@example.test',
            'password' => 'password123',
        ])->assertCreated();

        $user = User::whereEmail('profile-name@example.test')->firstOrFail();
        $this->assertSame('Nama Pengguna', $user->name);

        Sanctum::actingAs($user);

        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Nama Pengguna')
            ->assertJsonPath('data.email', 'profile-name@example.test');

        $this->getJson('/api/profile/status')
            ->assertOk()
            ->assertJsonPath('data.name', 'Nama Pengguna')
            ->assertJsonPath('data.is_profile_complete', true)
            ->assertJsonPath('data.profile_completion_required', false);
    }

    public function test_package_selection_preserves_registered_name_when_name_is_not_resent(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Nama Paket',
            'email' => 'package-name@example.test',
            'password' => 'password123',
            'phone' => '081234567890',
        ])->assertCreated();

        $user = User::whereEmail('package-name@example.test')->firstOrFail();
        $package = PaketUndangan::create([
            'code' => 'ruby',
            'jenis_paket' => 'Paket Ruby',
            'name_paket' => 'Paket Ruby',
            'price' => 100000,
            'masa_aktif' => 30,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/one-step', [
            'email' => 'package-name@example.test',
            'password' => 'password123',
            'phone' => '081234567890',
            'paket_undangan_id' => $package->id,
            'domain' => 'package-name-test',
        ])->assertOk();

        $this->assertSame('Nama Paket', $user->fresh()->name);
    }

    public function test_email_code_is_sent_without_being_exposed(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create(['verification_channel' => 'email']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/auth/verification/send', ['channel' => 'email'])
            ->assertOk()->assertJsonMissing(['code' => '123456']);
        Notification::assertSentTo($user, VerificationCodeNotification::class);
        $this->assertDatabaseCount('account_verification_tokens', 1);
    }

    public function test_whatsapp_verification_is_temporarily_unavailable(): void
    {
        $gateway = $this->mock(WhatsAppGateway::class);
        $gateway->shouldNotReceive('send');
        $user = User::factory()->unverified()->create(['phone' => '08123456789', 'verification_channel' => 'whatsapp']);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/auth/verification/send', ['channel' => 'whatsapp'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Verifikasi WhatsApp sementara tidak tersedia.');
    }

    public function test_wrong_expired_and_used_codes_are_rejected(): void
    {
        $user = User::factory()->unverified()->create(['verification_channel' => 'email']);
        Sanctum::actingAs($user);
        $token = $this->token($user, '123456');
        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => '000000'])
            ->assertStatus(422)->assertJsonPath('code', 'VERIFICATION_CODE_INVALID');
        $token->update(['expires_at' => now()->subMinute()]);
        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => '123456'])
            ->assertStatus(422)->assertJsonPath('code', 'VERIFICATION_CODE_EXPIRED');
        $token->update(['expires_at' => now()->addMinute()]);
        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => '123456'])->assertOk();
        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => '123456'])
            ->assertStatus(422)->assertJsonPath('code', 'VERIFICATION_CODE_INVALID');
    }

    public function test_verified_email_redirects_to_package_payment_selection(): void
    {
        $user = User::factory()->unverified()->create(['verification_channel' => 'email']);
        Sanctum::actingAs($user);
        $this->token($user, '123456');

        $this->postJson('/api/v1/auth/verification/verify', ['channel' => 'email', 'code' => '123456'])
            ->assertOk()
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.account_status', 'verified_no_invoice')
            ->assertJsonPath('data.next_step', 'select-package-payment-method')
            ->assertJsonPath('data.redirect_url', '/pilih-paket');
    }

    public function test_forgot_password_rejects_unknown_email_and_sends_for_known_email(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.test']);

        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'unknown@example.test', 'channel' => 'email']);
        $unknown->assertStatus(422)
            ->assertJsonPath('message', 'Email tidak terdaftar.')
            ->assertJsonValidationErrors('email');
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@example.test']);

        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@example.test', 'channel' => 'email']);
        $known->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Link reset kata sandi telah dikirim ke email Anda.');
        Notification::assertSentTo($user, CustomResetPasswordNotification::class, 1);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_password_reset_token_is_single_use_and_revokes_login_tokens(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.test', 'password' => Hash::make('old-password')]);
        $user->createToken('old');
        $token = Password::broker()->createToken($user);
        $payload = ['email' => $user->email, 'token' => $token,
            'password' => 'new-password', 'password_confirmation' => 'new-password'];
        $this->postJson('/api/v1/auth/reset-password', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Kata sandi berhasil diperbarui.');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertCount(0, $user->fresh()->tokens);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(422);
    }

    public function test_password_reset_rejects_wrong_token(): void
    {
        $user = User::factory()->create(['email' => 'wrong-token@example.test']);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => 'wrong-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'RESET_TOKEN_INVALID');
    }

    public function test_password_reset_rejects_confirmation_mismatch(): void
    {
        $user = User::factory()->create(['email' => 'mismatch@example.test']);
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

    public function test_user_can_login_with_new_password_after_reset(): void
    {
        $user = User::factory()->create([
            'email' => 'login-after-reset@example.test',
            'password' => Hash::make('old-password'),
        ]);
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

    private function token(User $user, string $code, string $purpose = 'account_verification'): AccountVerificationToken
    {
        return AccountVerificationToken::create(['user_id' => $user->id, 'channel' => 'email', 'purpose' => $purpose,
            'token_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'sent_at' => now()->subMinutes(2)]);
    }
}
