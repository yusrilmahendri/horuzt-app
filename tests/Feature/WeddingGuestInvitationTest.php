<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use ZipArchive;

class WeddingGuestInvitationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.frontend_url' => 'https://www.sena-digital.com',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        $this->withoutMiddleware();

        $this->createMinimalSchema();
    }

    public function test_create_guest_link_is_saved_and_can_be_scanned(): void
    {
        $user = $this->createWeddingOwner();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wedding-guests', [
            'domain' => 'nova-yusril',
            'guest_name' => 'h & hj',
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Link tamu berhasil dibuat.')
            ->assertJsonPath('data.guest_name', 'h & hj')
            ->assertJsonPath('data.guest_code', 'h-hj')
            ->assertJsonPath('data.domain', 'nova-yusril')
            ->assertJsonPath('data.invitation_url', 'https://www.sena-digital.com/wedding/nova-yusril?to=h-hj');

        $this->assertDatabaseHas('wedding_guests', [
            'user_id' => $user->id,
            'domain' => 'nova-yusril',
            'guest_name' => 'h & hj',
            'guest_code' => 'h-hj',
            'invitation_url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=h-hj',
        ]);

        $this->getJson('/api/v1/wedding-guests?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.guest_name', 'h & hj')
            ->assertJsonPath('data.0.guest_code', 'h-hj');

        $this->postJson('/api/v1/attendance/scan', [
            'url' => 'https://www.sena-digital.com/wedding/nova-yusril?to=h-hj',
        ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Kehadiran tamu berhasil dicatat.')
            ->assertJsonPath('data.guest_name', 'h & hj')
            ->assertJsonPath('data.guest_code', 'h-hj')
            ->assertJsonPath('data.attendance_status', 'hadir');

        $this->getJson('/api/v1/attendance/list?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.guest_name', 'h & hj')
            ->assertJsonPath('data.0.guest_code', 'h-hj');
    }

    public function test_duplicate_guest_code_gets_suffix_and_export_uses_database(): void
    {
        $user = $this->createWeddingOwner();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wedding-guests', [
            'domain' => 'nova-yusril',
            'guest_name' => 'h & hj',
        ])->assertCreated();

        $this->postJson('/api/v1/wedding-guests', [
            'domain' => 'nova-yusril',
            'guest_name' => 'h hj',
        ])
            ->assertCreated()
            ->assertJsonPath('data.guest_code', 'h-hj-2');

        $response = $this->getJson('/api/v1/wedding-guests/export?domain=nova-yusril')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.mime_type', 'text/csv');

        $csv = base64_decode((string) $response->json('data.content'));
        $this->assertStringContainsString('h & hj', $csv);
        $this->assertStringContainsString('h-hj-2', $csv);
    }

    public function test_import_guest_array_saves_to_wedding_guests(): void
    {
        $user = $this->createWeddingOwner();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wedding-guests/import', [
            'domain' => 'nova-yusril',
            'guests' => [
                ['guest_name' => 'Tamu Import'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.guest_code', 'tamu-import');

        $this->assertDatabaseHas('wedding_guests', [
            'user_id' => $user->id,
            'domain' => 'nova-yusril',
            'guest_name' => 'Tamu Import',
            'guest_code' => 'tamu-import',
        ]);
    }

    public function test_import_xlsx_saves_to_wedding_guests(): void
    {
        $user = $this->createWeddingOwner();
        Sanctum::actingAs($user);

        $this->post('/api/v1/wedding-guests/import', [
            'domain' => 'nova-yusril',
            'file' => new UploadedFile(
                $this->makeXlsx(['Nama Tamu', 'Excel Guest']),
                'guests.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.guest_code', 'excel-guest');

        $this->assertDatabaseHas('wedding_guests', [
            'user_id' => $user->id,
            'domain' => 'nova-yusril',
            'guest_name' => 'Excel Guest',
            'guest_code' => 'excel-guest',
        ]);
    }

    private function createWeddingOwner(): User
    {
        $user = User::create([
            'name' => 'Nova Yusril Owner',
            'email' => 'guest-'.str()->random(8).'@example.test',
            'password' => bcrypt('secret123'),
            'email_verified_at' => now(),
        ]);

        DB::table('settings')->insert([
            'user_id' => $user->id,
            'domain' => 'nova-yusril',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('acaras')->insert([
            'user_id' => $user->id,
            'nama_acara' => 'Resepsi',
            'tanggal_acara' => now()->toDateString(),
            'start_acara' => '18:00',
            'end_acara' => '20:00',
            'alamat' => 'Gedung Test',
            'link_maps' => 'https://maps.example.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
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
            $table->timestamps();
        });

        Schema::create('acaras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nama_acara')->nullable();
            $table->date('tanggal_acara')->nullable();
            $table->time('start_acara')->nullable();
            $table->time('end_acara')->nullable();
            $table->text('alamat')->nullable();
            $table->text('link_maps')->nullable();
            $table->timestamps();
        });

        Schema::create('wedding_guests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('guest_name');
            $table->string('guest_token', 64)->unique();
            $table->string('guest_code')->nullable();
            $table->string('domain');
            $table->string('invitation_url', 2048)->nullable();
            $table->timestamp('first_visit_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->boolean('attended')->default(false);
            $table->timestamp('attended_at')->nullable();
            $table->unsignedBigInteger('attended_acara_id')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_scans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('acara_id');
            $table->string('guest_name');
            $table->string('guest_identifier')->nullable();
            $table->enum('scan_type', ['qr_code', 'manual'])->default('qr_code');
            $table->timestamp('scanned_at');
            $table->unsignedBigInteger('scanned_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'acara_id', 'guest_identifier'], 'unique_guest_scan');
        });

        Schema::create('buku_tamus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nama');
            $table->string('status_kehadiran')->nullable();
            $table->integer('jumlah_tamu')->default(1);
            $table->boolean('is_approved')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    private function makeXlsx(array $firstColumnValues): string
    {
        $path = tempnam(sys_get_temp_dir(), 'guests').'.xlsx';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');

        $rows = '';
        foreach (array_values($firstColumnValues) as $index => $value) {
            $rowNumber = $index + 1;
            $escaped = htmlspecialchars($value, ENT_XML1);
            $rows .= '<row r="'.$rowNumber.'"><c r="A'.$rowNumber.'" t="inlineStr"><is><t>'.$escaped.'</t></is></c></row>';
        }

        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();

        return $path;
    }
}
