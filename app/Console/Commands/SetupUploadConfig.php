<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupUploadConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'upload:setup-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate configuration snippets for production upload setup';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Production Upload Configuration Setup ===');
        $this->newLine();

        // Generate Nginx configuration
        $this->info('1. NGINX Configuration:');
        $this->info('Add this to your Nginx server block:');
        $this->newLine();
        $this->line('```nginx');
        $this->line('server {');
        $this->line('    # Increase client body size for file uploads');
        $this->line('    client_max_body_size 6M;');
        $this->line('    ');
        $this->line('    # Increase timeouts for file uploads');
        $this->line('    client_body_timeout 60s;');
        $this->line('    client_header_timeout 60s;');
        $this->line('    ');
        $this->line('    location ~ \.php$ {');
        $this->line('        # Your existing PHP-FPM configuration');
        $this->line('        fastcgi_read_timeout 300;');
        $this->line('        fastcgi_send_timeout 300;');
        $this->line('    }');
        $this->line('}');
        $this->line('```');
        $this->newLine();

        // Generate Apache configuration
        $this->info('2. APACHE Configuration:');
        $this->info('Add this to your .htaccess or virtual host:');
        $this->newLine();
        $this->line('```apache');
        $this->line('# Increase file upload limits');
        $this->line('LimitRequestBody 6291456');
        $this->line('```');
        $this->newLine();

        // Generate PHP-FPM configuration
        $this->info('3. PHP-FPM Configuration:');
        $this->info('Add/modify these settings in your php.ini or PHP-FPM pool config:');
        $this->newLine();
        $this->line('```ini');
        $this->line('; File upload settings');
        $this->line('upload_max_filesize = 6M');
        $this->line('post_max_size = 6M');
        $this->line('max_file_uploads = 20');
        $this->line('max_execution_time = 300');
        $this->line('max_input_time = 300');
        $this->line('memory_limit = 256M');
        $this->line('```');
        $this->newLine();

        // Generate deployment script
        $this->info('4. Deployment Script:');
        $this->info('Create a deployment script with these commands:');
        $this->newLine();
        $this->line('```bash');
        $this->line('#!/bin/bash');
        $this->line('# After updating configuration files');
        $this->line('sudo systemctl reload nginx  # or apache2');
        $this->line('sudo systemctl restart php8.2-fpm  # adjust version as needed');
        $this->line('php artisan config:clear');
        $this->line('php artisan cache:clear');
        $this->line('php artisan upload:check-config');
        $this->line('```');
        $this->newLine();

        $this->info('5. Testing:');
        $this->line('After applying configurations, test with:');
        $this->line('- php artisan upload:check-config');
        $this->line('- Upload a 5MB file via API');
        $this->newLine();

        $this->warn('Important: Restart web server and PHP-FPM after making changes!');

        return 0;
    }
}
