<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckUploadConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:check-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check upload configuration settings for debugging';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Upload Configuration Check ===');
        $this->newLine();

        // PHP Configuration
        $this->info('PHP Configuration:');
        $this->table(
            ['Setting', 'Current Value', 'Recommended'],
            [
                ['upload_max_filesize', ini_get('upload_max_filesize'), '6M or higher'],
                ['post_max_size', ini_get('post_max_size'), '6M or higher'],
                ['max_file_uploads', ini_get('max_file_uploads'), '20 or higher'],
                ['max_execution_time', ini_get('max_execution_time'), '300 or higher'],
                ['memory_limit', ini_get('memory_limit'), '256M or higher'],
                ['max_input_time', ini_get('max_input_time'), '300 or higher'],
            ]
        );

        $this->newLine();

        // Laravel Configuration
        $this->info('Laravel Upload Configuration:');
        $maxSize = config('upload.max_file_size', 5222);
        $maxSizeMb = config('upload.max_file_size_mb', '5.1');
        $allowedTypes = implode(', ', config('upload.allowed_image_types', []));

        $this->table(
            ['Setting', 'Value'],
            [
                ['Max File Size (KB)', $maxSize],
                ['Max File Size (MB)', $maxSizeMb],
                ['Allowed Types', $allowedTypes],
                ['PHP Settings Applied', 'Via Middleware'],
            ]
        );

        $this->newLine();

        // Recommendations
        $this->info('Recommendations for Production:');
        $this->line('1. Check Nginx configuration: client_max_body_size should be 6m or higher');
        $this->line('2. Check Apache configuration: LimitRequestBody should be 6291456 or higher');
        $this->line('3. Restart web server after configuration changes');
        $this->line('4. Check PHP-FPM settings if using PHP-FPM');

        return 0;
    }
}
