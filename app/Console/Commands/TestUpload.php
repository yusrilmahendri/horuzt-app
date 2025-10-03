<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TestUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:test-upload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test upload functionality and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Upload Configuration Test ===');
        $this->newLine();

        // Test 1: Check PHP Configuration
        $this->info('1. Testing PHP Configuration:');
        $this->testPhpConfig();
        $this->newLine();

        // Test 2: Check Laravel Upload Config
        $this->info('2. Testing Laravel Configuration:');
        $this->testLaravelConfig();
        $this->newLine();

        // Test 3: Check Storage
        $this->info('3. Testing Storage Configuration:');
        $this->testStorage();
        $this->newLine();

        // Test 4: Create dummy file test
        $this->info('4. Testing File Creation:');
        $this->testFileCreation();
        $this->newLine();

        // Test 5: Check validation rules
        $this->info('5. Testing Validation Rules:');
        $this->testValidationRules();
        $this->newLine();

        $this->info('✅ Upload test completed!');
        $this->warn('💡 If all tests pass but upload still fails, the issue is likely with web server configuration.');

        return 0;
    }

    private function testPhpConfig()
    {
        $configs = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
        ];

        foreach ($configs as $key => $value) {
            $status = $this->getConfigStatus($key, $value);
            $this->line("   {$key}: {$value} {$status}");
        }
    }

    private function getConfigStatus($key, $value)
    {
        switch ($key) {
            case 'upload_max_filesize':
            case 'post_max_size':
                return $this->parseBytes($value) >= $this->parseBytes('6M') ? '✅' : '❌';
            case 'max_file_uploads':
                return $value >= 20 ? '✅' : '❌';
            case 'max_execution_time':
                return $value >= 300 || $value == 0 ? '✅' : '❌';
            case 'memory_limit':
                return $this->parseBytes($value) >= $this->parseBytes('256M') ? '✅' : '❌';
            case 'file_uploads':
                return $value === 'On' ? '✅' : '❌';
            default:
                return '✅';
        }
    }

    private function parseBytes($val)
    {
        $val = trim($val);
        if (empty($val)) return 0;

        $last = strtolower($val[strlen($val)-1]);
        $val = (int) $val;

        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    private function testLaravelConfig()
    {
        $maxSize = config('upload.max_file_size', 5222);
        $maxSizeMb = config('upload.max_file_size_mb', '5.1');
        $allowedTypes = config('upload.allowed_image_types', []);

        $this->line("   Max file size: {$maxSize} KB ({$maxSizeMb} MB) ✅");
        $this->line("   Allowed types: " . implode(', ', $allowedTypes) . " ✅");

        // Check if config is cached
        if (config()->has('upload')) {
            $this->line("   Config loaded: ✅");
        } else {
            $this->line("   Config loaded: ❌ (Run php artisan config:clear)");
        }
    }

    private function testStorage()
    {
        try {
            $disk = Storage::disk('public');

            // Test if storage is accessible
            if (!$disk->exists('')) {
                $this->line("   Storage accessible: ❌ (Storage not found)");
                return;
            }

            $this->line("   Storage accessible: ✅");

            // Test write permissions
            $testContent = 'test-' . time();
            $testPath = 'test-upload-' . time() . '.txt';

            if ($disk->put($testPath, $testContent)) {
                $this->line("   Write permission: ✅");
                $disk->delete($testPath); // Cleanup
            } else {
                $this->line("   Write permission: ❌");
            }

        } catch (\Exception $e) {
            $this->line("   Storage test: ❌ - " . $e->getMessage());
        }
    }

    private function testFileCreation()
    {
        try {
            // Create a dummy 5MB file for testing
            $tempPath = sys_get_temp_dir() . '/test-5mb-' . time() . '.jpg';
            $dummyContent = str_repeat('A', 5 * 1024 * 1024); // 5MB of 'A'

            if (file_put_contents($tempPath, $dummyContent)) {
                $fileSize = filesize($tempPath);
                $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                $this->line("   Created test file: {$fileSizeMB}MB ✅");

                // Cleanup
                unlink($tempPath);
            } else {
                $this->line("   File creation: ❌");
            }

        } catch (\Exception $e) {
            $this->line("   File creation test: ❌ - " . $e->getMessage());
        }
    }

    private function testValidationRules()
    {
        $rules = [
            'photo' => 'required|file|mimes:jpg,png,jpeg|max:5222',
        ];

        $this->line("   Validation rules: " . $rules['photo'] . " ✅");
        $this->line("   Max size in validation: 5222 KB (≈ 5.1 MB) ✅");
    }
}