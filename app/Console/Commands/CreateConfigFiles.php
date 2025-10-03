<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CreateConfigFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:create-config-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create .htaccess and php.ini files for Domanesia hosting';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating configuration files for Domanesia hosting...');
        $this->newLine();

        $publicPath = public_path();

        // Create .htaccess
        $this->createHtaccess($publicPath);

        // Create php.ini
        $this->createPhpIni($publicPath);

        $this->newLine();
        $this->info('✅ Configuration files created successfully!');
        $this->info('📁 Files location: ' . $publicPath);
        $this->newLine();
        $this->warn('📋 Next steps:');
        $this->line('1. Upload these files to your Domanesia public_html directory');
        $this->line('2. Run: php artisan config:clear');
        $this->line('3. Run: php artisan cache:clear');
        $this->line('4. Test upload functionality');

        return 0;
    }

    private function createHtaccess($publicPath)
    {
        $htaccessPath = $publicPath . '/.htaccess';

        // Read existing .htaccess if it exists
        $existingContent = '';
        if (File::exists($htaccessPath)) {
            $existingContent = File::get($htaccessPath);
            $this->info('📄 Existing .htaccess found, backing up...');
            File::copy($htaccessPath, $htaccessPath . '.backup.' . time());
        }

        $uploadConfig = '# Upload Configuration for Domanesia
# Increase PHP limits for file uploads
php_value upload_max_filesize 6M
php_value post_max_size 6M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
php_value max_file_uploads 20

# Additional Apache settings if supported
LimitRequestBody 6291456

';

        // Check if upload configuration already exists
        if (strpos($existingContent, 'upload_max_filesize') !== false) {
            $this->warn('⚠️  Upload configuration already exists in .htaccess');
            return;
        }

        // Default Laravel .htaccess content
        $laravelHtaccess = '<IfModule mod_rewrite.c>
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

        $finalContent = $uploadConfig . "\n" . ($existingContent ?: $laravelHtaccess);

        File::put($htaccessPath, $finalContent);
        $this->info('✅ .htaccess updated with upload configuration');
    }

    private function createPhpIni($publicPath)
    {
        $phpIniPath = $publicPath . '/php.ini';

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
error_log = error_log

; Session settings
session.cookie_httponly = On
session.use_only_cookies = On

; Additional settings for file uploads
file_uploads = On
auto_detect_line_endings = On';

        if (File::exists($phpIniPath)) {
            $this->info('📄 Existing php.ini found, backing up...');
            File::copy($phpIniPath, $phpIniPath . '.backup.' . time());
        }

        File::put($phpIniPath, $phpIniContent);
        $this->info('✅ php.ini created with upload configuration');
    }
}
