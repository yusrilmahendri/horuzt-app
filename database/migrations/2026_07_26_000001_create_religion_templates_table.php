<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('religion_templates', function (Blueprint $table) {
            $table->id();
            $table->string('religion_key', 50)->unique();
            $table->string('religion_name');
            $table->text('opening_greeting')->nullable();
            $table->text('closing_greeting')->nullable();
            $table->text('invitation_intro')->nullable();
            $table->text('whatsapp_message')->nullable();
            $table->text('quote_text')->nullable();
            $table->string('quote_source')->nullable();
            $table->text('prayer_text')->nullable();
            $table->text('blessing_text')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();
        $supported = config('religion_content.supported', []);
        $templates = config('religion_content.templates', []);

        foreach ($supported as $key => $name) {
            if ($key === 'custom') {
                continue;
            }

            DB::table('religion_templates')->insert(array_merge([
                'religion_key' => $key,
                'religion_name' => $name,
                'active' => true,
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ], collect(config('religion_content.fields', []))
                ->mapWithKeys(fn (string $field) => [$field => $templates[$key][$field] ?? null])
                ->all()));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('religion_templates');
    }
};
