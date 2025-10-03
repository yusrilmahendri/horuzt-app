# 📋 Panduan Upload 5MB di Domanesia

## 🚀 Quick Fix - Langkah yang Sudah Dilakukan

Sistem sudah membuat file konfigurasi otomatis untuk Domanesia hosting. Berikut yang sudah disiapkan:

### ✅ File yang Sudah Dibuat:
1. **`.htaccess`** - Konfigurasi Apache untuk upload limit
2. **`php.ini`** - Konfigurasi PHP untuk upload limit
3. **Commands Laravel** - Tools untuk testing dan debugging

## 📤 Langkah Deploy ke Domanesia

### 1. Upload File via FTP/File Manager Domanesia:

**Upload ke folder `public_html/` di Domanesia:**
```
public/.htaccess  → public_html/.htaccess
public/php.ini   → public_html/php.ini
```

### 2. Via Terminal Domanesia (Jika Ada Akses SSH):
```bash
# Jalankan script deployment
./deploy-upload-fix.sh

# Atau manual:
php artisan config:clear
php artisan cache:clear
php artisan upload:check-config
```

### 3. Via File Manager Domanesia:
1. Login ke **Control Panel Domanesia**
2. Buka **File Manager**
3. Masuk ke folder **public_html/**
4. Upload/Replace file `.htaccess` dengan yang sudah dibuat
5. Upload file `php.ini` baru
6. Restart website (jika ada opsi)

## 🔧 Konfigurasi yang Diterapkan

### .htaccess (Apache):
```apache
php_value upload_max_filesize 6M
php_value post_max_size 6M
php_value max_execution_time 300
php_value max_input_time 300
php_value memory_limit 256M
php_value max_file_uploads 20
LimitRequestBody 6291456
```

### php.ini:
```ini
upload_max_filesize = 6M
post_max_size = 6M
max_file_uploads = 20
max_execution_time = 300
max_input_time = 300
memory_limit = 256M
file_uploads = On
```

## 🧪 Testing Commands

```bash
# Cek konfigurasi saat ini
php artisan upload:check-config

# Setup khusus Domanesia
php artisan upload:domanesia-setup

# Test upload functionality
php artisan upload:test-upload

# Buat ulang file config
php artisan upload:create-config-files
```

## 🆘 Troubleshooting

### Jika Masih Error 422 "Upload Failed":

1. **Cek Control Panel Domanesia:**
   - Masuk ke **PHP Settings**
   - Pastikan versi PHP minimal 8.1
   - Cek ada limit tambahan di hosting panel

2. **Cek Log Error:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

3. **Alternatif via Control Panel:**
   - Buka **PHP Configuration** di Domanesia
   - Set manual:
     - `upload_max_filesize: 6M`
     - `post_max_size: 6M`
     - `max_execution_time: 300`

4. **Contact Support Domanesia:**
   Jika masih bermasalah, minta support untuk:
   - Enable file upload sampai 6MB
   - Cek web server limits (Nginx/Apache)
   - Pastikan tidak ada firewall yang block

## 📱 API Testing

Test endpoint yang sudah diperbaiki:
```
POST /api/v1/user/submission-galery
Content-Type: multipart/form-data

Form fields:
- photo: [file up to 5MB]
- nama_foto: "Test Photo"
- url_video: (optional)
```

Error handling sudah diperbaiki untuk memberikan informasi debug yang lebih detail.

## 🔍 Debug Info

Controller sudah diupdate untuk memberikan informasi debug jika upload gagal:
```json
{
  "message": "The photo failed to upload.",
  "errors": {"photo": ["The photo failed to upload."]},
  "debug": {
    "post_max_size": "6M",
    "upload_max_filesize": "6M",
    "max_file_uploads": "20"
  }
}
```

---

**💡 Tips:** Domanesia kadang butuh 5-10 menit untuk menerapkan perubahan konfigurasi. Tunggu sebentar setelah upload file konfigurasi sebelum test lagi.