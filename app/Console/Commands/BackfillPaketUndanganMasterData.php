<?php

namespace App\Console\Commands;

use App\Models\PaketUndangan;
use Illuminate\Console\Command;

class BackfillPaketUndanganMasterData extends Command
{
    protected $signature = 'paket-undangan:sync-master {--dry-run : Show changes without writing to database}';

    protected $description = 'Synchronize paket_undangans master labels from stable package code.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $updatedRows = 0;
        $missingCodes = [];

        foreach (array_keys(PaketUndangan::PACKAGE_MAP) as $code) {
            $paket = PaketUndangan::query()->where('code', $code)->first();

            if (! $paket) {
                $missingCodes[] = $code;
                continue;
            }

            $expectedJenis = PaketUndangan::jenisPaketFromCode($code);
            $expectedName = PaketUndangan::displayLabelFromCode($code);

            $changes = [];
            if ($paket->getRawOriginal('jenis_paket') !== $expectedJenis) {
                $changes['jenis_paket'] = $expectedJenis;
            }
            if ($paket->getRawOriginal('name_paket') !== $expectedName) {
                $changes['name_paket'] = $expectedName;
            }

            if ($changes === []) {
                $this->line("No change for code={$code} (id={$paket->id})");
                continue;
            }

            if ($isDryRun) {
                $this->warn("DRY RUN code={$code} (id={$paket->id}) -> " . json_encode($changes));
                continue;
            }

            $paket->update($changes);
            $updatedRows++;
            $this->info("Updated code={$code} (id={$paket->id})");
        }

        if ($missingCodes !== []) {
            $this->warn('Missing package rows for codes: ' . implode(', ', $missingCodes));
        }

        $this->info($isDryRun
            ? 'Dry run finished.'
            : "Sync finished. Updated rows: {$updatedRows}");

        return self::SUCCESS;
    }
}
