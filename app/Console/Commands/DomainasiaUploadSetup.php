<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DomainasiaUploadSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:domanesia-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup upload configuration for Domanesia hosting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Domanesia Upload Configuration Setup ===');
        $this->newLine();

        // Check current PHP configuration
        $this->info('Current PHP Configuration on Server:');
        $this->table(
            ['Setting', 'Current Value', 'Status'],
            [
                ['upload_max_filesize', ini_get('upload_max_filesize'), $this->getStatus(ini_get('upload_max_filesize'), '6M')],
                ['post_max_size', ini_get('post_max_size'), $this->getStatus(ini_get('post_max_size'), '6M')],
                ['max_file_uploads', ini_get('max_file_uploads'), ini_get('max_file_uploads') >= 20 ? '✅ OK' : '❌ Too Low'],
                ['max_execution_time', ini_get('max_execution_time'), ini_get('max_execution_time') >= 300 || ini_get('max_execution_time') == 0 ? '✅ OK' : '❌ Too Low'],
                ['memory_limit', ini_get('memory_limit'), $this->getMemoryStatus(ini_get('memory_limit'))],
            ]
        );

        $this->newLine();

        // Generate .htaccess for shared hosting
        $this->info('1. Create/Update .htaccess file in public directory:');
        $this->generateHtaccess();

        $this->newLine();

        // Generate php.ini for shared hosting
        $this->info('2. Create php.ini file in public directory:');
        $this->generatePhpIni();

        $this->newLine();

        // Check if files need to be created
        $this->info('3. Files to create/update:');
        $this->checkFiles();

        $this->newLine();

        // Deployment steps
        $this->info('4. Deployment Steps for Domanesia:');
        $this->showDeploymentSteps();

        return 0;
    }

    private function getStatus($current, $required)
    {
        $currentBytes = $this->parseBytes($current);
        $requiredBytes = $this->parseBytes($required);

        return $currentBytes >= $requiredBytes ? '✅ OK' : '❌ Too Small';
    }

    private function getMemoryStatus($current)
    {
        $currentBytes = $this->parseBytes($current);
        $requiredBytes = $this->parseBytes('256M');

        return $currentBytes >= $requiredBytes ? '✅ OK' : '❌ Too Small';
    }

    private function parseBytes($val)
    {
        $val = trim($val);
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

    private function generateHtaccess()
    {
        $htaccessContent = '# Upload Configuration for Domanesia
# Increase PHP limits for file uploads
php_value upload_max_filesize 6M
php_value post_max_size 6M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
php_value max_file_uploads 20

# Additional Apache settings if supported
LimitRequestBody 6291456

# Laravel Rewrite Rules (existing)
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>';

        $this->line($htaccessContent);
    }

    private function generatePhpIni()
    {
        $phpIniContent = '; PHP Configuration for Domanesia Upload
; File upload settings
upload_max_filesize = 6M
post_max_size = 6M
max_file_uploads = 20
max_execution_time = 300
max_input_time = 300
memory_limit = 256M

; Error reporting (for production)
display_errors = Off
log_errors = On

; Session settings
session.cookie_httponly = On
session.use_only_cookies = On';

        $this->line($phpIniContent);
    }

    private function checkFiles()
    {
        $publicPath = public_path();
        $htaccessPath = $publicPath . '/.htaccess';
        $phpIniPath = $publicPath . '/php.ini';

        $this->table(
            ['File', 'Location', 'Status', 'Action'],
            [
                ['.htaccess', $htaccessPath, file_exists($htaccessPath) ? '✅ EXISTS' : '❌ MISSING', 'Update with upload settings'],
                ['php.ini', $phpIniPath, file_exists($phpIniPath) ? '✅ EXISTS' : '❌ MISSING', 'Create with upload settings'],
            ]
        );
    }

    private function showDeploymentSteps()
    {
        $steps = [
            '1. Run: php artisan upload:create-config-files',
            '2. Upload .htaccess to public_html/ directory',
            '3. Upload php.ini to public_html/ directory',
            '4. Run: php artisan config:clear',
            '5. Run: php artisan cache:clear',
            '6. Test upload with: php artisan upload:test-upload',
            '7. Check final config: php artisan upload:check-config'
        ];

        foreach ($steps as $step) {
            $this->line($step);
        }
    }
}