<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'religion_code')) {
                $table->string('religion_code', 50)->nullable()->index();
            }

            foreach ($this->customColumns() as $column) {
                if (! Schema::hasColumn('settings', $column)) {
                    $table->text($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = array_filter(
                array_merge(['religion_code'], $this->customColumns()),
                fn (string $column) => Schema::hasColumn('settings', $column)
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    private function customColumns(): array
    {
        return [
            'religion_opening_greeting',
            'religion_closing_greeting',
            'religion_invitation_intro',
            'religion_whatsapp_message',
            'religion_quote_text',
            'religion_quote_source',
            'religion_prayer_text',
            'religion_blessing_text',
        ];
    }
};
