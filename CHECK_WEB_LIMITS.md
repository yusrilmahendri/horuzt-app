# Verify Web Server Upload Limits

## Why CLI Check Shows Wrong Values

The command `php artisan app:check-upload-limits` runs in **CLI mode** and reads from `/opt/alt/php82/etc/php.ini`.

The `.user.ini` file only affects **web requests** (HTTP), not CLI commands.

---

## Method 1: Create PHP Info Endpoint (TEMPORARY)

**⚠️ Remove this after testing!**

1. Create a temporary route to check web PHP settings:

```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt
```

2. Add this route to `routes/web.php` (at the bottom):

```php
// TEMPORARY - Remove after testing
Route::get('/phpinfo-test-delete-me', function () {
    return response()->json([
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'max_input_time' => ini_get('max_input_time'),
    ]);
});
```

3. Visit the URL in your browser:
```
https://horuzt.senadigi.com/phpinfo-test-delete-me
```

4. Check the JSON response - it should show:
```json
{
  "upload_max_filesize": "60M",
  "post_max_size": "200M",
  "memory_limit": "512M",
  "max_execution_time": "600",
  "max_input_time": "600"
}
```

5. **Delete the route after confirming!**

---

## Method 2: Check .user.ini Content

Verify the file contains correct values:

```bash
ssh senadigi@lecce
cat /home/senadigi/public_html/horuzt/public/.user.ini
```

Expected output:
```ini
; PHP Upload Configuration for Production
; Generated on: [timestamp]
upload_max_filesize = 60M
post_max_size = 200M
memory_limit = 512M
max_execution_time = 600
max_input_time = 600
max_file_uploads = 20
```

---

## Method 3: Wait and Test Real Upload

The settings may already be working for web requests. Just test:

1. Upload a large file via your API endpoint:
```bash
curl -X POST https://horuzt.senadigi.com/api/v1/user/submission-galery \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "photo=@large-image.jpg" \
  -F "nama_foto=Production test"
```

2. Monitor logs:
```bash
ssh senadigi@lecce
tail -f /home/senadigi/horuzt/storage/logs/laravel.log
```

3. If upload succeeds → Settings are working!
4. If upload fails → Check error message in logs

---

## Method 4: Check Apache/PHP-FPM Process

See which PHP configuration the web server is using:

```bash
ssh senadigi@lecce
cd /home/senadigi/public_html/horuzt/public

# Create a temporary PHP file
cat > check-limits.php << 'EOF'
<?php
header('Content-Type: application/json');
echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'server_api' => PHP_SAPI,
    'loaded_ini' => php_ini_loaded_file(),
]);
?>
EOF

# Visit in browser
echo "Visit: https://horuzt.senadigi.com/check-limits.php"
```

Then visit `https://horuzt.senadigi.com/check-limits.php` in your browser.

**Delete the file after testing:**
```bash
rm /home/senadigi/public_html/horuzt/public/check-limits.php
```

---

## Expected Results

If `.user.ini` is working, the web request should show:
- `server_api`: "fpm-fcgi" or "cgi-fcgi" (not "cli")
- `upload_max_filesize`: "60M"
- `post_max_size`: "200M"

If still showing 4M/8M values:
1. Check if PHP-FPM has reloaded (wait 10 more minutes)
2. Contact Sena Digital to enable .user.ini support
3. Use cPanel MultiPHP INI Editor instead

---

## Quick Test Command

Use this one-liner to test from the command line:

```bash
ssh senadigi@lecce "cd /home/senadigi/horuzt && cat public/.user.ini"
```

This confirms the file exists and has correct content.

---

## Next Steps

1. **Option A:** Use Method 1 (temporary route) to check web PHP settings quickly
2. **Option B:** Use Method 3 (real upload test) to verify end-to-end
3. **Option C:** Use Method 4 (check-limits.php) for detailed PHP info

I recommend **Method 1** (temporary route) because:
- Fast to implement
- Shows actual web server PHP settings
- Easy to remove after testing
- No security risk if removed promptly

---

## If Still Showing Low Limits

If web requests still show 4M/8M after confirming .user.ini exists:

1. **Check PHP mode:**
   ```bash
   php -v  # Should show FastCGI or FPM
   ```

2. **Restart PHP-FPM (if you have permission):**
   ```bash
   sudo systemctl restart ea-php82
   # or
   /scripts/restartsrv_apache
   ```

3. **Use cPanel instead:**
   - Log into cPanel: https://horuzt.senadigi.com:2083
   - Find "MultiPHP INI Editor"
   - Set values manually

4. **Contact Sena Digital support:**
   ```
   Subject: Enable .user.ini Support for PHP 8.2
   
   My account needs .user.ini support enabled for custom PHP settings.
   Domain: horuzt.senadigi.com
   
   Required settings:
   - upload_max_filesize = 60M
   - post_max_size = 200M
   - max_execution_time = 600
   ```

---

**Bottom Line:** The CLI check is misleading. Test with actual web requests to confirm if `.user.ini` is working.
