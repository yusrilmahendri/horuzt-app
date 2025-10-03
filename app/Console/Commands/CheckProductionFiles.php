<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckProductionFiles extends Command
{
    protected $signature = 'upload:check-production-files';
    protected $description = 'Check if upload configuration files exist in production';

    public function handle()
    {
        $this->info('=== Checking Production Upload Files ===');
        $this->newLine();

        // Check files in current directory (production)
        $currentDir = base_path();
        $publicDir = public_path();

        // Files to check
        $files = [
            'public/.htaccess' => $publicDir . '/.htaccess',
            'public/php.ini' => $publicDir . '/php.ini',
            '.htaccess (root)' => $currentDir . '/.htaccess',
            'php.ini (root)' => $currentDir . '/php.ini',
        ];

        $this->info('📁 File Status Check:');
        foreach ($files as $name => $path) {
            $exists = File::exists($path);
            $status = $exists ? '✅ EXISTS' : '❌ MISSING';
            $size = $exists ? ' (' . File::size($path) . ' bytes)' : '';
            $this->line("   {$name}: {$status}{$size}");

            // Check content if exists
            if ($exists && (str_contains($name, '.htaccess') || str_contains($name, 'php.ini'))) {
                $content = File::get($path);
                $hasUploadConfig = str_contains($content, 'upload_max_filesize');
                $configStatus = $hasUploadConfig ? '✅ Has upload config' : '❌ No upload config';
                $this->line("     → Content: {$configStatus}");
            }
        }

        $this->newLine();

        // Check PHP configuration
        $this->info('🔧 Current PHP Configuration:');
        $phpSettings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ];

        foreach ($phpSettings as $setting => $value) {
            $this->line("   {$setting}: {$value}");
        }

        $this->newLine();

        // Recommendations
        $this->warn('📋 Next Actions:');

        $htaccessExists = File::exists($publicDir . '/.htaccess');
        $phpIniExists = File::exists($publicDir . '/php.ini');

        if (!$htaccessExists) {
            $this->line('1. Create/edit public_html/.htaccess with upload config');
        }

        if (!$phpIniExists) {
            $this->line('2. Create public_html/php.ini with upload config');
        }

        if ($htaccessExists && $phpIniExists) {
            $this->line('✅ Files exist! Run: php artisan config:clear && php artisan cache:clear');
        }

        return 0;
    }
}
