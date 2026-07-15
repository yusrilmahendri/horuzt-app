<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppGateway;
use App\Models\AccountVerificationToken;
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
        $response = $this->postJson('/api/v1/register', ['email' => 'new@example.test', 'password' => 'password123']);
        $response->assertCreated()->assertJsonPath('data.user.email_verified_at', null);
        $this->assertFalse(User::whereEmail('new@example.test')->firstOrFail()->isAccountVerified());
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

    public function test_forgot_password_response_does_not_disclose_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.test']);
        $known = $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'known@example.test', 'channel' => 'email']);
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['identifier' => 'unknown@example.test', 'channel' => 'email']);
        $known->assertOk();
        $unknown->assertOk();
        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame('Link reset kata sandi telah dikirim ke email Anda.', $known->json('message'));
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
