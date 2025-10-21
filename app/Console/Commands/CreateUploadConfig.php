<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CreateUploadConfig extends Command
{
    protected $signature = 'app:create-upload-config {--force : Overwrite existing files}';
    protected $description = 'Create .user.ini and update .htaccess for production upload configuration';

    public function handle()
    {
        $this->info('Creating upload configuration files for production...');
        $this->newLine();

        $force = $this->option('force');

        // Create .user.ini
        $this->createUserIni($force);

        // Update .htaccess
        $this->updateHtaccess($force);

        $this->newLine();
        $this->info('=== Next Steps ===');
        $this->line('1. Upload .user.ini to your public/ directory on production');
        $this->line('2. Upload updated .htaccess to your public/ directory on production');
        $this->line('3. Wait 5-10 minutes for PHP-FPM to reload configuration');
        $this->line('4. Run: php artisan app:check-upload-limits to verify');
        $this->line('5. Test file upload through your API');
        $this->newLine();
        $this->warn('Note: Some hosting providers may require you to configure these settings through cPanel.');
        $this->line('If .user.ini does not work, contact Sena Digital support with these values.');

        return 0;
    }

    private function createUserIni($force)
    {
        $path = public_path('.user.ini');

        if (file_exists($path) && !$force) {
            $this->warn('.user.ini already exists. Use --force to overwrite.');
            return;
        }

        $content = <<<'INI'
; PHP Upload Configuration for Large Files
; This file configures PHP-FPM upload limits
; Wait 5-10 minutes after uploading for changes to take effect

; Maximum size of uploaded file (60MB)
upload_max_filesize = 60M

; Maximum size of POST data (200MB - must be larger than upload_max_filesize)
post_max_size = 200M

; Memory limit for script execution (512MB)
memory_limit = 512M

; Maximum execution time in seconds (10 minutes)
max_execution_time = 600

; Maximum input time in seconds (10 minutes)
max_input_time = 600

; Maximum number of files per upload
max_file_uploads = 20

; Enable file uploads
file_uploads = On
INI;

        file_put_contents($path, $content);
        $this->info('✓ Created .user.ini at: ' . $path);
        $this->line('  Settings: 60M upload, 200M POST, 512M memory, 600s timeout');
    }

    private function updateHtaccess($force)
    {
        $path = public_path('.htaccess');

        if (!file_exists($path)) {
            $this->error('.htaccess not found at: ' . $path);
            return;
        }

        $current = file_get_contents($path);

        // Check if upload directives already exist
        if (strpos($current, 'LimitRequestBody') !== false && !$force) {
            $this->warn('.htaccess already contains upload directives. Use --force to update.');
            return;
        }

        // Remove old upload directives if they exist
        $current = preg_replace(
            '/# Upload Configuration Start.*?# Upload Configuration End\n/s',
            '',
            $current
        );

        // Add upload configuration after mod_rewrite
        $uploadConfig = <<<'HTACCESS'

# Upload Configuration Start
# Configure Apache to accept large file uploads
<IfModule mod_php.c>
    php_value upload_max_filesize 60M
    php_value post_max_size 200M
    php_value memory_limit 512M
    php_value max_execution_time 600
    php_value max_input_time 600
</IfModule>

# Increase Apache request body limit to 200MB
LimitRequestBody 209715200

# Timeout configuration
Timeout 600
# Upload Configuration End

HTACCESS;

        // Insert before the closing tag
        $updated = str_replace('</IfModule>', $uploadConfig . '</IfModule>', $current);

        // Backup original
        if (!$force) {
            $backupPath = public_path('.htaccess.backup.' . date('YmdHis'));
            copy($path, $backupPath);
            $this->line('  Backup created: ' . basename($backupPath));
        }

        file_put_contents($path, $updated);
        $this->info('✓ Updated .htaccess with upload directives');
        $this->line('  Settings: 200MB limit, 600s timeout');
    }
}
