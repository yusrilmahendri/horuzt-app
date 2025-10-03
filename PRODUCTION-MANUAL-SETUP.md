# 🚀 Manual Upload Fix untuk Domanesia Production

## Step-by-Step Manual Setup (Tanpa Script)

### 1. Buat file .htaccess di production

Di server production, buat atau edit file `.htaccess` di folder `public_html/`:

```bash
nano public_html/.htaccess
```

**Tambahkan konfigurasi ini di ATAS file .htaccess:**

```apache
# Upload Configuration for Domanesia
# Increase PHP limits for file uploads
php_value upload_max_filesize 6M
php_value post_max_size 6M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
php_value max_file_uploads 20

# Additional Apache settings if supported
LimitRequestBody 6291456

# --- Sisanya tetap (Laravel rewrite rules) ---
```

### 2. Buat file php.ini di production

```bash
nano public_html/php.ini
```

**Isi dengan:**

```ini
; PHP Configuration for Domanesia Upload
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
auto_detect_line_endings = On
```

### 3. Clear Laravel Cache

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### 4. Test Configuration

```bash
php artisan upload:check-config
```

### 5. Test Upload

Coba upload foto 5MB via API endpoint:
```
POST /api/v1/user/submission-galery
```

## 🔧 Troubleshooting Commands

Jika masih error, jalankan untuk debugging:

```bash
# Cek konfigurasi PHP saat ini
php -i | grep upload_max_filesize
php -i | grep post_max_size

# Cek Laravel config
php artisan upload:domanesia-setup

# Test upload functionality
php artisan upload:test-upload
```

## ⚠️ Catatan Penting

1. **File lokasi:** Pastikan file di `public_html/` bukan di subfolder
2. **Tunggu 5-10 menit** setelah upload config sebelum test
3. **Backup:** Simpan backup `.htaccess` lama jika ada
4. **Contact Support:** Jika masih gagal, hubungi support Domanesia untuk cek limits server

## 🆘 Alternative: Via Control Panel

Jika edit manual tidak work, coba via **Control Panel Domanesia**:
1. Login → **PHP Settings** 
2. Set manual:
   - `upload_max_filesize: 6M`
   - `post_max_size: 6M`
   - `max_execution_time: 300`
