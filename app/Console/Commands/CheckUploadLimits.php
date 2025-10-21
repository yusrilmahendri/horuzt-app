<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckUploadLimits extends Command
{
    protected $signature = 'app:check-upload-limits';
    protected $description = 'Check PHP and server upload configuration limits';

    public function handle()
    {
        $this->info('=== PHP Upload Configuration ===');
        $this->newLine();

        $settings = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'file_uploads' => ini_get('file_uploads') ? 'Enabled' : 'Disabled',
            'max_file_uploads' => ini_get('max_file_uploads'),
        ];

        $table = [];
        foreach ($settings as $key => $value) {
            $status = $this->getStatus($key, $value);
            $table[] = [$key, $value, $status];
        }

        $this->table(['Setting', 'Current Value', 'Status'], $table);

        $this->newLine();
        $this->info('=== Server Information ===');
        $this->line('PHP Version: ' . PHP_VERSION);
        $this->line('Server API: ' . PHP_SAPI);
        $this->line('Loaded php.ini: ' . php_ini_loaded_file());
        
        $scannedFiles = php_ini_scanned_files();
        if ($scannedFiles) {
            $this->line('Additional .ini files: ' . $scannedFiles);
        }

        $this->newLine();
        $this->info('=== Recommendations ===');
        
        $uploadMax = $this->convertToBytes($settings['upload_max_filesize']);
        $postMax = $this->convertToBytes($settings['post_max_size']);
        $memoryLimit = $this->convertToBytes($settings['memory_limit']);

        if ($uploadMax < 52428800) { // 50MB
            $this->warn('⚠ upload_max_filesize is less than 50MB');
            $this->line('  Recommended: 60M or higher');
        }

        if ($postMax < 52428800) {
            $this->warn('⚠ post_max_size is less than 50MB');
            $this->line('  Recommended: 200M or higher (must be larger than upload_max_filesize)');
        }

        if ($postMax < $uploadMax) {
            $this->error('✗ post_max_size must be larger than upload_max_filesize!');
        }

        if ($memoryLimit < 268435456 && $memoryLimit != -1) { // 256MB
            $this->warn('⚠ memory_limit is less than 256MB');
            $this->line('  Recommended: 512M or higher');
        }

        if ($settings['max_execution_time'] < 300) {
            $this->warn('⚠ max_execution_time is less than 300 seconds');
            $this->line('  Recommended: 600 seconds for large uploads');
        }

        $this->newLine();
        $this->info('=== Configuration Files ===');
        
        $userIniPath = public_path('.user.ini');
        $htaccessPath = public_path('.htaccess');
        
        if (file_exists($userIniPath)) {
            $this->line('✓ .user.ini exists at: ' . $userIniPath);
        } else {
            $this->warn('✗ .user.ini not found. Create it with: php artisan app:create-upload-config');
        }

        if (file_exists($htaccessPath)) {
            $this->line('✓ .htaccess exists at: ' . $htaccessPath);
            
            $htaccessContent = file_get_contents($htaccessPath);
            if (strpos($htaccessContent, 'LimitRequestBody') === false) {
                $this->warn('✗ .htaccess missing upload directives. Update it with: php artisan app:create-upload-config');
            } else {
                $this->line('✓ .htaccess contains upload directives');
            }
        }

        $this->newLine();
        $this->info('=== Test Upload Path ===');
        $storagePath = storage_path('app/public');
        $this->line('Storage path: ' . $storagePath);
        $this->line('Writable: ' . (is_writable($storagePath) ? 'Yes' : 'No'));
        
        $diskSpace = disk_free_space($storagePath);
        $this->line('Free disk space: ' . $this->formatBytes($diskSpace));

        return 0;
    }

    private function getStatus($key, $value)
    {
        $recommendations = [
            'upload_max_filesize' => 62914560, // 60MB
            'post_max_size' => 209715200, // 200MB
            'memory_limit' => 536870912, // 512MB
            'max_execution_time' => 300,
        ];

        if (!isset($recommendations[$key])) {
            return '—';
        }

        $bytes = $this->convertToBytes($value);
        
        if ($bytes == -1) {
            return '✓ Unlimited';
        }

        if ($bytes >= $recommendations[$key]) {
            return '✓ OK';
        }

        return '⚠ Low';
    }

    private function convertToBytes($value)
    {
        if ($value == '-1' || $value == -1) {
            return -1;
        }

        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
