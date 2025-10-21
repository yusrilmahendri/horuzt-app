# Fix CORS Error di Production

## Error yang Terjadi

```
Access to XMLHttpRequest at 'https://cloud-api.sena-digital.com/api/v1/paket-undangan' 
from origin 'https://www.sena-digital.com' has been blocked by CORS policy: 
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## Root Cause

Config CORS tidak ter-cache di production setelah deploy perubahan file upload.

---

## Quick Fix (Production Server)

SSH ke production dan jalankan:

```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt

# Clear semua cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Re-cache untuk production
php artisan config:cache
php artisan route:cache
```

**Setelah itu test lagi dari frontend.**

---

## Jika Masih Error

### Option 1: Pastikan .env Production Benar

Check file `.env` di production:

```bash
cat .env | grep APP_ENV
```

Pastikan:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cloud-api.sena-digital.com
```

Jika salah, edit dan cache ulang:
```bash
nano .env
# Edit nilai di atas
php artisan config:cache
```

---

### Option 2: Aktifkan Custom CORS Middleware

Jika Laravel CORS tidak bekerja, aktifkan custom middleware:

Edit `app/Http/Kernel.php` di production:

```php
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\CorsMiddleware::class,  // <- Uncomment ini
],
```

Lalu cache ulang:
```bash
php artisan config:cache
php artisan route:cache
```

---

### Option 3: Tambahkan Header di .htaccess

Edit `public/.htaccess` tambahkan sebelum `# Handle Authorization Header`:

```apache
# CORS Headers
<IfModule mod_headers.c>
    SetEnvIf Origin "^https?://(www\.)?sena-digital\.com$" AccessControlAllowOrigin=$0
    Header always set Access-Control-Allow-Origin %{AccessControlAllowOrigin}e env=AccessControlAllowOrigin
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"
    Header always set Access-Control-Allow-Credentials "true"
</IfModule>
```

---

## Verify CORS Headers

Test dari terminal lokal:

```bash
curl -I -X OPTIONS https://cloud-api.sena-digital.com/api/v1/paket-undangan \
  -H "Origin: https://www.sena-digital.com" \
  -H "Access-Control-Request-Method: GET"
```

**Expected Response:**
```
Access-Control-Allow-Origin: https://www.sena-digital.com
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With
Access-Control-Allow-Credentials: true
```

Jika tidak ada header `Access-Control-Allow-Origin`, berarti CORS tidak aktif.

---

## Check dari Browser Console

Buka browser console (F12) dan jalankan:

```javascript
fetch('https://cloud-api.sena-digital.com/api/v1/paket-undangan', {
  method: 'GET',
  headers: {
    'Origin': 'https://www.sena-digital.com'
  }
})
.then(r => r.json())
.then(d => console.log(d))
.catch(e => console.error(e));
```

Jika CORS bekerja, akan ada response data.
Jika error, akan muncul CORS policy error lagi.

---

## Langkah Debugging

1. **Clear cache** (command di atas) ✓
2. **Test dengan curl** untuk cek header CORS
3. **Jika tidak ada header**, aktifkan custom middleware atau .htaccess
4. **Cache ulang** setelah perubahan
5. **Test dari frontend**

---

## Catatan Penting

- Config CORS ada di `config/cors.php`
- Origins yang diizinkan sudah termasuk `https://www.sena-digital.com`
- Middleware `HandleCors` sudah aktif di global middleware
- Sanctum `EnsureFrontendRequestsAreStateful` membutuhkan origin match untuk credentials

---

## Quick Commands Reference

```bash
# Production: Clear dan re-cache
ssh senadigi@lecce
cd /home/senadigi/horuzt
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache

# Local: Test CORS header
curl -I -X OPTIONS https://cloud-api.sena-digital.com/api/v1/paket-undangan \
  -H "Origin: https://www.sena-digital.com"

# Lihat log jika masih error
tail -f /home/senadigi/horuzt/storage/logs/laravel.log
```

---

**Most Likely Fix:** Hanya perlu `php artisan config:cache` di production.
