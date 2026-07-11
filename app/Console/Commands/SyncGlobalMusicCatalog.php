<?php

namespace App\Console\Commands;

use App\Services\GlobalMusicCatalogSyncService;
use Illuminate\Console\Command;

class SyncGlobalMusicCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'music:sync-global {--seed-mock : Seed local mock global tracks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync global music catalog into local cache table';

    public function handle(GlobalMusicCatalogSyncService $syncService): int
    {
        $result = $this->option('seed-mock')
            ? $syncService->seedMock()
            : $syncService->sync();

        $this->info('Global music catalog sync completed.');
        foreach ($result as $key => $value) {
            $this->line("- {$key}: {$value}");
        }

        return self::SUCCESS;
    }
}
