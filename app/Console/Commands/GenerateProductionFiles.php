<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateProductionFiles extends Command
{
    protected $signature = 'upload:generate-production-files';
    protected $description = 'Generate content for .htaccess and php.ini to copy-paste in production';

    public function handle()
    {
        $this->info('=== Production Files Content ===');
        $this->newLine();

        $this->info('📄 .htaccess Content (Add to TOP of existing .htaccess):');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('# Upload Configuration for Domanesia');
        $this->line('# Increase PHP limits for file uploads');
        $this->line('php_value upload_max_filesize 6M');
        $this->line('php_value post_max_size 6M');
        $this->line('php_value max_execution_time 300');
        $this->line('php_value max_input_time 300');
        $this->line('php_value memory_limit 256M');
        $this->line('php_value max_file_uploads 20');
        $this->line('');
        $this->line('# Additional Apache settings if supported');
        $this->line('LimitRequestBody 6291456');
        $this->line('');
        $this->info('═══════════════════════════════════════════════════');

        $this->newLine();

        $this->info('📄 php.ini Content (Create new file):');
        $this->info('═══════════════════════════════════════════════════');
        $this->line('; PHP Configuration for Domanesia Upload');
        $this->line('; File upload settings');
        $this->line('upload_max_filesize = 6M');
        $this->line('post_max_size = 6M');
        $this->line('max_file_uploads = 20');
        $this->line('max_execution_time = 300');
        $this->line('max_input_time = 300');
        $this->line('memory_limit = 256M');
        $this->line('');
        $this->line('; Error reporting (for production)');
        $this->line('display_errors = Off');
        $this->line('log_errors = On');
        $this->line('error_log = error_log');
        $this->line('');
        $this->line('; Session settings');
        $this->line('session.cookie_httponly = On');
        $this->line('session.use_only_cookies = On');
        $this->line('');
        $this->line('; Additional settings for file uploads');
        $this->line('file_uploads = On');
        $this->line('auto_detect_line_endings = On');
        $this->info('═══════════════════════════════════════════════════');

        $this->newLine();

        $this->warn('📋 Manual Steps:');
        $this->line('1. Copy .htaccess content above to public_html/.htaccess (at TOP)');
        $this->line('2. Create public_html/php.ini with content above');
        $this->line('3. Run: php artisan config:clear && php artisan cache:clear');
        $this->line('4. Wait 5-10 minutes for changes to take effect');
        $this->line('5. Test upload via API');

        $this->newLine();

        $this->info('🧪 Test Commands:');
        $this->line('php artisan upload:check-config');
        $this->line('php artisan upload:test-upload');

        return 0;
    }
}
