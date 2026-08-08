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

    public function test_user_religion_content_update_preserves_explicit_empty_greetings(): void
    {
        $user = $this->createUser('empty-greeting-user@example.test');
        Sanctum::actingAs($user);

        ReligionTemplate::create([
            'religion_key' => 'islam',
            'religion_name' => 'Islam',
            'opening_greeting' => 'Default Islam',
            'closing_greeting' => 'Default Penutup Islam',
            'whatsapp_message' => 'Default WA {{guest_name}}',
            'active' => true,
        ]);

        Setting::create([
            'user_id' => $user->id,
            'religion_code' => 'islam',
            'religion_opening_greeting' => 'Pembuka awal',
            'religion_closing_greeting' => 'Penutup awal',
        ]);

        $this->putJson('/api/v1/user/religion-content', [
            'opening_greeting' => 'Pembuka custom',
            'closing_greeting' => 'Penutup custom',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', 'Pembuka custom')
            ->assertJsonPath('data.resolved.closing_greeting', 'Penutup custom');

        $this->putJson('/api/v1/user/religion-content', [
            'opening_greeting' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.custom.opening_greeting', '')
            ->assertJsonPath('data.custom.closing_greeting', 'Penutup custom')
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', 'Penutup custom');

        $this->assertSame('', Setting::where('user_id', $user->id)->value('religion_opening_greeting'));
        $this->assertSame('Penutup custom', Setting::where('user_id', $user->id)->value('religion_closing_greeting'));

        $this->putJson('/api/v1/user/religion-content', [
            'closing_greeting' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', '');

        $this->putJson('/api/v1/user/religion-content', [
            'opening_greeting' => 'Pembuka baru',
            'closing_greeting' => 'Penutup baru',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', 'Pembuka baru')
            ->assertJsonPath('data.resolved.closing_greeting', 'Penutup baru');

        $this->putJson('/api/v1/user/religion-content', [
            'opening_greeting' => null,
            'closing_greeting' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.custom.opening_greeting', '')
            ->assertJsonPath('data.custom.closing_greeting', '')
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', '');

        $this->getJson('/api/v1/user/religion-content')
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', '');

        $this->putJson('/api/v1/user/religion-content', [
            'whatsapp_message' => 'Custom WA {{guest_name}}',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', '')
            ->assertJsonPath('data.resolved.whatsapp_message', 'Custom WA {{guest_name}}');

        $this->getJson('/api/v1/user/religion-content')
            ->assertOk()
            ->assertJsonPath('data.resolved.opening_greeting', '')
            ->assertJsonPath('data.resolved.closing_greeting', '');
    }

    public function test_admin_religion_template_update_preserves_null_and_omitted_fields(): void
    {
        $admin = $this->createUser('empty-greeting-admin@example.test');
        Sanctum::actingAs($admin);

        $template = ReligionTemplate::create([
            'religion_key' => 'islam',
            'religion_name' => 'Islam',
            'opening_greeting' => 'Admin pembuka',
            'closing_greeting' => 'Admin penutup',
            'active' => true,
        ]);

        $this->putJson('/api/v1/admin/religion-templates/'.$template->id, [
            'opening_greeting' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.opening_greeting', null)
            ->assertJsonPath('data.closing_greeting', 'Admin penutup');

        $template->refresh();
        $this->assertNull($template->opening_greeting);
        $this->assertSame('Admin penutup', $template->closing_greeting);
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

        Schema::create('pernikahans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('salam_pembuka')->nullable();
            $table->string('salam_wa_atas')->nullable();
            $table->string('salam_wa_bawah')->nullable();
            $table->timestamps();
        });

        Schema::create('qoutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name')->nullable();
            $table->text('qoute')->nullable();
            $table->timestamps();
        });
    }
}
