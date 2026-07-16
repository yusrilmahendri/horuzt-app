<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\PaymentLog;
use App\Models\User;
use App\Notifications\MidtransPaymentStatusNotification;
use App\Services\MidtransService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MidtransCustomerDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_midtrans_payload_uses_user_name_for_customer_details(): void
    {
        $user = $this->verifiedUser('Sena Digital User');
        $invitation = $this->invitationFor($user);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                return ($params['customer_details']['first_name'] ?? null) === 'Sena'
                    && ($params['customer_details']['last_name'] ?? null) === 'Digital User'
                    && ($params['customer_details']['email'] ?? null) === 'midtrans-user@example.test'
                    && ($params['customer_details']['phone'] ?? null) === '08123456789'
                    && ! str_contains(json_encode($params), 'Guest');
            }))
            ->andReturn('snap-token-test');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
            'customer_details' => [
                'first_name' => 'Guest',
                'last_name' => 'Override',
                'phone' => '08123456789',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token-test');

        $payload = json_decode(PaymentLog::firstOrFail()->request_payload, true);
        $this->assertSame('Sena', $payload['customer_details']['first_name']);
        $this->assertSame('Digital User', $payload['customer_details']['last_name']);
        $this->assertStringNotContainsString('Guest', json_encode($payload));
    }

    public function test_midtrans_requires_complete_profile_name_before_creating_invoice(): void
    {
        $user = $this->verifiedUser(null, 'old-user@example.test');
        $invitation = $this->invitationFor($user);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldNotReceive('createTransaction');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'PROFILE_INCOMPLETE')
            ->assertJsonPath('data.missing_fields.0', 'name');

        $this->assertDatabaseCount('payment_logs', 0);
    }

    public function test_midtrans_profile_validation_uses_fresh_database_name(): void
    {
        $staleUser = $this->verifiedUser(null, 'fresh-name@example.test');
        $invitation = $this->invitationFor($staleUser);
        User::whereKey($staleUser->id)->update(['name' => 'Nama Dari Database']);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')
            ->once()
            ->with(Mockery::on(function (array $params): bool {
                return ($params['customer_details']['first_name'] ?? null) === 'Nama'
                    && ($params['customer_details']['last_name'] ?? null) === 'Dari Database';
            }))
            ->andReturn('snap-token-fresh-name');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($staleUser);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ])
            ->assertCreated()
            ->assertJsonPath('data.snap_token', 'snap-token-fresh-name');
    }

    public function test_midtrans_create_snap_token_is_idempotent_for_active_existing_transaction(): void
    {
        $user = $this->verifiedUser('Idempotent User', 'idempotent@example.test');
        $invitation = $this->invitationFor($user);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')->once()->andReturn('snap-token-idempotent');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $first = $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ]);

        $first->assertCreated()
            ->assertJsonPath('data.reused', false)
            ->assertJsonPath('data.snap_token', 'snap-token-idempotent');

        $orderId = $first->json('data.order_id');

        $second = $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ]);

        $second->assertOk()
            ->assertJsonPath('data.reused', true)
            ->assertJsonPath('data.snap_token', 'snap-token-idempotent')
            ->assertJsonPath('data.order_id', $orderId);

        $this->assertSame($orderId, $invitation->fresh()->order_id);
        $this->assertDatabaseCount('payment_logs', 1);
    }

    public function test_midtrans_create_snap_token_rejects_paid_invoice_without_new_token(): void
    {
        $user = $this->verifiedUser('Paid User', 'paid-midtrans@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update([
            'payment_status' => 'paid',
            'payment_confirmed_at' => now(),
            'order_id' => 'PAID-ORDER-1',
        ]);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldNotReceive('createTransaction');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_ALREADY_PAID')
            ->assertJsonPath('redirect_url', '/dashboard/overview');

        $this->assertDatabaseCount('payment_logs', 0);
    }

    public function test_midtrans_expired_token_is_marked_expired_and_recreated_once(): void
    {
        $user = $this->verifiedUser('Expired Token User', 'expired-token@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'OLD-MIDTRANS-ORDER']);

        PaymentLog::create([
            'user_id' => $user->id,
            'invitation_id' => $invitation->id,
            'order_id' => 'OLD-MIDTRANS-ORDER',
            'event_type' => 'token_request',
            'transaction_status' => 'pending',
            'gross_amount' => $invitation->paketUndangan->price,
            'response_payload' => json_encode([
                'snap_token' => 'old-expired-token',
                'expires_at' => now()->subMinute()->toIso8601String(),
            ]),
        ]);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldReceive('createTransaction')
            ->once()
            ->with(Mockery::on(fn (array $params): bool => ($params['transaction_details']['order_id'] ?? null) !== 'OLD-MIDTRANS-ORDER'))
            ->andReturn('new-snap-token');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ])
            ->assertCreated()
            ->assertJsonPath('data.reused', false)
            ->assertJsonPath('data.snap_token', 'new-snap-token');

        $this->assertSame('expire', PaymentLog::where('order_id', 'OLD-MIDTRANS-ORDER')->firstOrFail()->transaction_status);
        $this->assertDatabaseCount('payment_logs', 2);
        $this->assertNotSame('OLD-MIDTRANS-ORDER', $invitation->fresh()->order_id);
    }

    public function test_user_cannot_create_midtrans_token_for_another_users_invoice(): void
    {
        $owner = $this->verifiedUser('Owner User', 'owner-midtrans@example.test');
        $intruder = $this->verifiedUser('Intruder User', 'intruder-midtrans@example.test');
        $invitation = $this->invitationFor($owner);

        $midtrans = Mockery::mock(MidtransService::class);
        $midtrans->shouldNotReceive('createTransaction');
        $this->app->instance(MidtransService::class, $midtrans);

        Sanctum::actingAs($intruder);

        $this->postJson('/api/v1/midtrans/create-snap-token', [
            'invitation_id' => $invitation->id,
            'amount' => (float) $invitation->paketUndangan->price,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('invitation_id');
    }

    public function test_manual_payment_is_blocked_when_active_midtrans_transaction_exists(): void
    {
        $user = $this->verifiedUser('Manual Switch User', 'manual-switch@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'ACTIVE-MIDTRANS-ORDER']);

        PaymentLog::create([
            'user_id' => $user->id,
            'invitation_id' => $invitation->id,
            'order_id' => 'ACTIVE-MIDTRANS-ORDER',
            'event_type' => 'token_request',
            'transaction_status' => 'pending',
            'gross_amount' => $invitation->paketUndangan->price,
            'response_payload' => json_encode([
                'snap_token' => 'active-snap-token',
                'expires_at' => now()->addHour()->toIso8601String(),
            ]),
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user/tagihan', [
            'user_id' => $user->id,
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'PAYMENT_METHOD_ALREADY_SELECTED')
            ->assertJsonPath('data.payment_method', 'midtrans')
            ->assertJsonPath('data.snap_token', 'active-snap-token');
    }

    public function test_midtrans_webhook_pending_qris_saves_details_and_sends_pending_email_once(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('QRIS User', 'qris-webhook@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'QRIS-ORDER-1']);

        $payload = $this->webhookPayload($invitation, [
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-qris-1',
            'payment_type' => 'qris',
            'acquirer' => 'gopay',
            'expiry_time' => now()->addHour()->format('Y-m-d H:i:s'),
            'actions' => [
                ['name' => 'generate-qr-code', 'method' => 'GET', 'url' => 'https://midtrans.test/qr'],
            ],
        ]);

        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertOk();
        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertOk();

        $this->assertSame('pending', $invitation->fresh()->payment_status);
        $processed = PaymentLog::where('event_type', 'webhook_processed')->where('payment_type', 'qris')->firstOrFail();
        $details = json_decode($processed->response_payload, true)['payment_details'];
        $this->assertSame('qris', $processed->payment_type);
        $this->assertSame('gopay', $details['acquirer']);

        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, 1);
        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, fn ($notification) => $notification->status() === 'pending');
    }

    public function test_midtrans_webhook_pending_bank_transfer_stores_va_number_in_email_payload(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('VA User', 'va-webhook@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'VA-ORDER-1']);

        $payload = $this->webhookPayload($invitation, [
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-va-1',
            'payment_type' => 'bank_transfer',
            'va_numbers' => [
                ['bank' => 'bca', 'va_number' => '1234567890'],
            ],
        ]);

        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertOk();

        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, function ($notification) {
            $payload = $notification->payload();

            return $notification->status() === 'pending'
                && ($payload['payment_details']['bank'] ?? null) === 'bca'
                && ($payload['payment_details']['va_number'] ?? null) === '1234567890';
        });
    }

    public function test_midtrans_webhook_settlement_sends_success_email_and_not_pending_email(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('Paid Webhook User', 'paid-webhook@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'SETTLEMENT-ORDER-1']);

        $this->postJson('/api/v1/midtrans/webhook', $this->webhookPayload($invitation, [
            'transaction_status' => 'settlement',
            'transaction_id' => 'trx-settlement-1',
            'payment_type' => 'qris',
        ]))->assertOk();

        $this->assertSame('paid', $invitation->fresh()->payment_status);
        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, 1);
        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, fn ($notification) => $notification->status() === 'paid');
        Notification::assertNotSentTo($user, MidtransPaymentStatusNotification::class, fn ($notification) => $notification->status() === 'pending');
    }

    public function test_midtrans_webhook_expire_sends_expired_email(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('Expired Webhook User', 'expired-webhook@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'EXPIRE-ORDER-1']);

        $this->postJson('/api/v1/midtrans/webhook', $this->webhookPayload($invitation, [
            'transaction_status' => 'expire',
            'transaction_id' => 'trx-expire-1',
            'payment_type' => 'bank_transfer',
        ]))->assertOk();

        $this->assertSame('failed', $invitation->fresh()->payment_status);
        Notification::assertSentTo($user, MidtransPaymentStatusNotification::class, fn ($notification) => $notification->status() === 'expired');
    }

    public function test_midtrans_webhook_pending_after_settlement_does_not_downgrade_or_email_pending(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('Late Pending User', 'late-pending@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update([
            'order_id' => 'LATE-PENDING-ORDER',
            'payment_status' => 'paid',
            'payment_confirmed_at' => now(),
        ]);

        $this->postJson('/api/v1/midtrans/webhook', $this->webhookPayload($invitation, [
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-late-pending',
            'payment_type' => 'qris',
        ]))->assertOk();

        $this->assertSame('paid', $invitation->fresh()->payment_status);
        Notification::assertNothingSent();
    }

    public function test_midtrans_webhook_mail_failure_still_returns_ok_and_logs_error(): void
    {
        Log::spy();
        $user = $this->verifiedUser('Mail Fail User', 'mail-fail@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'MAIL-FAIL-ORDER']);

        $this->app->instance(Dispatcher::class, new class implements Dispatcher {
            public function send($notifiables, $notification): void
            {
                throw new \RuntimeException('SMTP gagal');
            }

            public function sendNow($notifiables, $notification, ?array $channels = null): void
            {
                throw new \RuntimeException('SMTP gagal');
            }
        });

        $this->postJson('/api/v1/midtrans/webhook', $this->webhookPayload($invitation, [
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-mail-fail',
            'payment_type' => 'qris',
        ]))->assertOk();

        $this->assertSame('pending', $invitation->fresh()->payment_status);
        Log::shouldHaveReceived('error')->with('Failed to send Midtrans payment notification email', Mockery::type('array'));
    }

    public function test_midtrans_webhook_invalid_signature_is_rejected(): void
    {
        Notification::fake();
        $user = $this->verifiedUser('Invalid Signature User', 'invalid-signature@example.test');
        $invitation = $this->invitationFor($user);
        $invitation->update(['order_id' => 'INVALID-SIGNATURE-ORDER']);

        $payload = $this->webhookPayload($invitation, [
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-invalid-signature',
            'payment_type' => 'qris',
        ]);
        $payload['signature_key'] = 'invalid';

        $this->postJson('/api/v1/midtrans/webhook', $payload)->assertForbidden();
        Notification::assertNothingSent();
    }

    private function verifiedUser(?string $name, string $email = 'midtrans-user@example.test'): User
    {
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '08123456789',
            'email_verified_at' => now(),
            'verification_channel' => 'email',
        ]);
        $user->assignRole('user');

        return $user;
    }

    private function invitationFor(User $user): Invitation
    {
        $package = PaketUndangan::firstOrCreate(
            ['code' => 'ruby'],
            [
                'jenis_paket' => 'Paket Ruby',
                'name_paket' => 'Paket Ruby',
                'price' => 100000,
                'masa_aktif' => 30,
            ]
        );

        return Invitation::create([
            'user_id' => $user->id,
            'paket_undangan_id' => $package->id,
            'kode_pemesanan' => '#MIDTRANS-NAME-'.$user->id,
            'status' => 'step1',
            'payment_status' => 'pending',
            'package_price_snapshot' => $package->price,
            'package_duration_snapshot' => $package->masa_aktif,
            'package_features_snapshot' => [
                'code' => $package->code,
                'name_paket' => $package->name_paket,
            ],
        ]);
    }

    private function webhookPayload(Invitation $invitation, array $overrides = []): array
    {
        config(['midtrans.server_key' => 'test-server-key']);

        $orderId = $invitation->order_id ?: $invitation->kode_pemesanan;
        $statusCode = (string) ($overrides['status_code'] ?? '200');
        $grossAmount = (string) ($overrides['gross_amount'] ?? number_format((float) $invitation->paketUndangan->price, 2, '.', ''));
        $signature = hash('sha512', $orderId.$statusCode.$grossAmount.config('midtrans.server_key'));

        return array_merge([
            'order_id' => $orderId,
            'transaction_status' => 'pending',
            'transaction_id' => 'trx-'.$invitation->id,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'payment_type' => 'qris',
            'transaction_time' => now()->format('Y-m-d H:i:s'),
            'signature_key' => $signature,
        ], $overrides);
    }
}
