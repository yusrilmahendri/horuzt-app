<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAccountIsVerified;
use App\Models\ReligionTemplate;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Middleware\RoleMiddleware;
use Tests\TestCase;

class ReligionTemplateTest extends TestCase
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
        $this->withoutMiddleware([
            EnsureAccountIsVerified::class,
            RoleMiddleware::class,
        ]);

        $this->createMinimalSchema();
    }

    public function test_admin_can_manage_religion_template(): void
    {
        $admin = $this->createUser('admin@example.test');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/religion-templates', [
            'religion_key' => 'kristen',
            'religion_name' => 'Kristen',
            'opening_heading' => 'Salam sejahtera.',
            'opening_text' => 'Dengan sukacita kami mengundang Anda.',
            'whatsapp_message' => 'Halo {{guest_name}}, undangan: {{invitation_url}}',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Template agama berhasil dibuat.')
            ->assertJsonPath('data.religion_key', 'kristen')
            ->assertJsonPath('data.content.opening_heading', 'Salam sejahtera.')
            ->assertJsonPath('data.content.opening_text', 'Dengan sukacita kami mengundang Anda.');

        $templateId = (int) $response->json('data.id');

        $this->putJson('/api/v1/admin/religion-templates/'.$templateId, [
            'quote_text' => 'Kasih tidak berkesudahan.',
        ])
            ->assertOk()
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.quote_text', 'Kasih tidak berkesudahan.');

        $this->patchJson('/api/v1/admin/religion-templates/'.$templateId.'/status', [
            'active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.version', 3);

        $this->deleteJson('/api/v1/admin/religion-templates/'.$templateId)
            ->assertOk()
            ->assertJsonPath('message', 'Template agama berhasil dihapus.')
            ->assertJsonPath('data.id', $templateId);

        $this->assertDatabaseMissing('religion_templates', [
            'id' => $templateId,
        ]);
    }

    public function test_admin_template_rejects_unknown_placeholder(): void
    {
        $admin = $this->createUser('placeholder-admin@example.test');
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/religion-templates', [
            'religion_key' => 'islam',
            'religion_name' => 'Islam',
            'whatsapp_message' => 'Halo {{nama_tamu}}',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Placeholder template agama tidak valid.');
    }

    public function test_admin_template_checks_unique_key_after_normalization(): void
    {
        $admin = $this->createUser('duplicate-admin@example.test');
        Sanctum::actingAs($admin);

        ReligionTemplate::create([
            'religion_key' => 'kristen',
            'religion_name' => 'Kristen',
            'active' => true,
        ]);

        $this->postJson('/api/v1/admin/religion-templates', [
            'religion_key' => 'christian',
            'religion_name' => 'Kristen Alias',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Religion key sudah digunakan.');
    }

    public function test_user_religion_content_separates_default_custom_and_resolved_content(): void
    {
        $user = $this->createUser('user@example.test');
        Sanctum::actingAs($user);

        ReligionTemplate::create([
            'religion_key' => 'universal',
            'religion_name' => 'Universal',
            'opening_greeting' => 'Default universal',
            'closing_greeting' => 'Penutup universal',
            'active' => true,
        ]);
        ReligionTemplate::create([
            'religion_key' => 'islam',
            'religion_name' => 'Islam',
            'opening_greeting' => 'Default Islam',
            'whatsapp_message' => 'Assalamualaikum {{guest_name}}',
            'active' => true,
        ]);
        Setting::create([
            'user_id' => $user->id,
            'religion_code' => 'islam',
            'religion_opening_greeting' => 'Custom pembuka',
        ]);

        $this->getJson('/api/v1/user/religion-content')
            ->assertOk()
            ->assertJsonPath('data.religion_key', 'islam')
            ->assertJsonPath('data.default_template.opening_heading', 'Default Islam')
            ->assertJsonPath('data.custom_content.opening_heading', 'Custom pembuka')
            ->assertJsonPath('data.resolved_content.opening_heading', 'Custom pembuka')
            ->assertJsonPath('data.resolved_content.closing_text', 'Penutup universal');
    }

    private function createUser(string $email): User
    {
        return User::create([
            'name' => 'Religion Template User',
            'email' => $email,
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('domain')->nullable();
            $table->string('religion_code', 50)->nullable();
            foreach (config('religion_content.fields', []) as $field) {
                $table->text('religion_'.$field)->nullable();
            }
            $table->timestamps();
        });

        Schema::create('religion_templates', function (Blueprint $table) {
            $table->id();
            $table->string('religion_key', 50)->unique();
            $table->string('religion_name');
            foreach (config('religion_content.fields', []) as $field) {
                $table->text($field)->nullable();
            }
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }
}
