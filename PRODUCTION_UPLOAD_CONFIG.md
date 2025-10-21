# Production File Upload Configuration Guide

## Problem

Large file uploads fail on Sena Digital production server while working locally. This occurs because production PHP and web server have restrictive upload limits.

## Root Cause

1. Server PHP configuration (php.ini) restricts upload size
2. Web server (Apache/Nginx) has request body size limits
3. Middleware ini_set() fails on shared hosting (permission denied)

## Solution

Configure upload limits through .user.ini and .htaccess files instead of relying on runtime ini_set().

---

## Step 1: Check Current Production Limits

SSH into your production server and run:

```bash
cd /home/senadigi/horuzt
php artisan app:check-upload-limits
```

This command shows:
- Current PHP upload limits
- Server configuration
- Recommendations for fixes
- Missing configuration files

---

## Step 2: Create Configuration Files

On your local machine, run:

```bash
php artisan app:create-upload-config
```

This creates:
- `.user.ini` in public/ directory (for PHP-FPM)
- Updates `.htaccess` with Apache directives

Configuration values:
- upload_max_filesize: 60M
- post_max_size: 200M
- memory_limit: 512M
- max_execution_time: 600 seconds

---

## Step 3: Deploy to Production

### Option A: Direct Upload (Recommended)

1. Upload files to production:
```bash
# From local machine
scp public/.user.ini senadigi@lecce:/home/senadigi/horuzt/public/
scp public/.htaccess senadigi@lecce:/home/senadigi/horuzt/public/
```

2. Verify files exist:
```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt/public
ls -la .user.ini .htaccess
```

3. Set correct permissions:
```bash
chmod 644 .user.ini
chmod 644 .htaccess
```

### Option B: Git Deployment

1. Commit the files:
```bash
git add public/.user.ini public/.htaccess
git commit -m "Add production upload configuration"
git push origin main
```

2. Pull on production:
```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt
git pull origin main
```

---

## Step 4: Wait for PHP-FPM Reload

PHP-FPM reads .user.ini on script execution. Changes take effect:
- Immediately for new requests (if PHP-FPM restarts)
- Within 5-10 minutes (automatic reload)

To force immediate reload, restart PHP-FPM (if you have permission):
```bash
# Check if you can restart
sudo systemctl restart php-fpm
# or
sudo service php8.1-fpm restart
```

If you do not have permission, wait 10 minutes.

---

## Step 5: Verify Configuration

After waiting, check if limits updated:

```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt
php artisan app:check-upload-limits
```

Expected output should show:
- upload_max_filesize: 60M
- post_max_size: 200M
- memory_limit: 512M

---

## Step 6: Test Upload

Test with actual file upload via your API:

```bash
curl -X POST https://your-domain.com/api/v1/user/submission-galery \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "photo=@large-image.jpg" \
  -F "nama_foto=test upload"
```

Check logs if it fails:
```bash
tail -f storage/logs/laravel.log
```

---

## Alternative: cPanel Configuration

If .user.ini does not work, configure through cPanel:

1. Log into cPanel (usually https://your-domain.com:2083)
2. Find "Select PHP Version" or "MultiPHP INI Editor"
3. Set these values:
   - upload_max_filesize: 60M
   - post_max_size: 200M
   - memory_limit: 512M
   - max_execution_time: 600
   - max_input_time: 600

4. Save and wait 5 minutes

---

## Troubleshooting

### Issue: .user.ini not taking effect

Check PHP mode:
```bash
php -v
```

If it shows CGI/FastCGI, .user.ini should work.
If it shows Apache module (mod_php), use .htaccess only.

### Issue: .htaccess causes 500 error

Your server may not allow LimitRequestBody directive.

Remove these lines from .htaccess:
```apache
LimitRequestBody 209715200
Timeout 600
```

### Issue: Still getting upload errors

Check actual error in logs:
```bash
tail -100 storage/logs/laravel.log
```

Common errors:
- "POST Content-Length exceeds the limit" → Increase post_max_size
- "Maximum execution time exceeded" → Increase max_execution_time
- "Allowed memory size exhausted" → Increase memory_limit

### Issue: Permission to create .user.ini denied

Contact Sena Digital support with these requirements:
```
Subject: Increase PHP Upload Limits for Account

Please configure these PHP settings for my account:
- upload_max_filesize = 60M
- post_max_size = 200M
- memory_limit = 512M
- max_execution_time = 600
- max_input_time = 600

Domain: your-domain.com
Reason: Laravel application requires large file uploads
```

---

## Environment Variables (Optional)

Add to production .env file for flexible configuration:

```env
PHP_UPLOAD_MAX_FILESIZE=60M
PHP_POST_MAX_SIZE=200M
PHP_MAX_EXECUTION_TIME=600
PHP_MEMORY_LIMIT=512M
PHP_MAX_FILE_UPLOADS=20
```

These values are used by the middleware if ini_set() is allowed.

---

## Files Modified

1. `app/Console/Commands/CheckUploadLimits.php` (new)
   - Diagnostic command to check server limits

2. `app/Console/Commands/CreateUploadConfig.php` (new)
   - Creates .user.ini and updates .htaccess

3. `app/Http/Middleware/LargeFileHandler.php` (updated)
   - Added error logging for ini_set() failures

4. `config/upload.php` (updated)
   - Increased default PHP settings
   - Added environment variable support

5. `public/.user.ini` (created by command)
   - PHP-FPM configuration

6. `public/.htaccess` (updated by command)
   - Apache upload directives

---

## Testing Checklist

After deployment:

- [ ] Files .user.ini and .htaccess uploaded to production
- [ ] Waited 10 minutes for PHP-FPM reload
- [ ] Ran php artisan app:check-upload-limits
- [ ] Verified upload_max_filesize = 60M
- [ ] Verified post_max_size = 200M
- [ ] Tested actual file upload via API
- [ ] Checked logs for errors
- [ ] Confirmed file appears in storage/app/public/photos/

---

## Quick Commands Reference

```bash
# Local: Generate config files
php artisan app:create-upload-config

# Production: Check current limits
php artisan app:check-upload-limits

# Production: Upload config files
scp public/.user.ini senadigi@lecce:/home/senadigi/horuzt/public/
scp public/.htaccess senadigi@lecce:/home/senadigi/horuzt/public/

# Production: Verify files
ls -la /home/senadigi/horuzt/public/.user.ini

# Production: Watch logs during upload test
tail -f storage/logs/laravel.log
```

---

## Important Notes

1. .user.ini only works with PHP-FPM or CGI mode
2. Changes take 5-10 minutes to activate
3. post_max_size must be larger than upload_max_filesize
4. memory_limit must be larger than post_max_size
5. If server blocks these configurations, contact hosting support

---

**Last Updated:** October 21, 2025  
**Tested On:** Sena Digital / Domanesia cPanel hosting  
**Status:** Production ready
