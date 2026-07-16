<?php

namespace Tests\Feature;

use App\Models\Invitation;
use App\Models\PaketUndangan;
use App\Models\PaymentLog;
use App\Models\User;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
