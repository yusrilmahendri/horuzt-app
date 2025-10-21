# Production .env Configuration for CORS Fix

Tambahkan environment variables ini ke file `.env` di production server:

```env
# App Configuration
APP_URL=https://cloud-api.sena-digital.com

# Session Configuration untuk Cross-Subdomain
SESSION_DOMAIN=.sena-digital.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=www.sena-digital.com,sena-digital.com,cloud-api.sena-digital.com
```

## Penjelasan:

### SESSION_DOMAIN=.sena-digital.com
- **Leading dot (.)** memungkinkan cookie dibaca oleh semua subdomain
- Frontend `www.sena-digital.com` dapat membaca cookie dari `cloud-api.sena-digital.com`

### SESSION_SECURE_COOKIE=true
- Cookie hanya dikirim via HTTPS (required untuk production)

### SESSION_SAME_SITE=none
- Membolehkan cookie dikirim dalam cross-site request
- **Wajib** untuk API dan Frontend di subdomain berbeda
- Harus dikombinasikan dengan `SESSION_SECURE_COOKIE=true`

### SANCTUM_STATEFUL_DOMAINS
- List domain yang boleh akses API dengan stateful authentication
- Pisahkan dengan koma tanpa spasi
- Include semua variasi domain (www dan non-www)

---

## Cara Deploy:

### 1. Commit dan Push ke Git
```bash
# Local machine
git add config/cors.php config/sanctum.php config/session.php
git commit -m "Fix CORS for cross-subdomain authentication"
git push origin main
```

### 2. Pull di Production
```bash
ssh senadigi@lecce
cd /home/senadigi/horuzt
git pull origin main
```

### 3. Update .env di Production
```bash
# Edit .env
nano .env
```

**Tambahkan baris berikut:**
```env
SESSION_DOMAIN=.sena-digital.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=none
SANCTUM_STATEFUL_DOMAINS=www.sena-digital.com,sena-digital.com,cloud-api.sena-digital.com
```

**Pastikan juga ada:**
```env
APP_URL=https://cloud-api.sena-digital.com
APP_ENV=production
APP_DEBUG=false
```

Save dengan: `Ctrl+O`, `Enter`, `Ctrl+X`

### 4. Clear dan Cache Config
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan config:cache
php artisan route:cache
```

### 5. Test dari Frontend
Akses `https://www.sena-digital.com/login` dan coba login.

---

## Verifikasi CORS Headers

Test preflight request:
```bash
curl -I -X OPTIONS https://cloud-api.sena-digital.com/api/v1/login \
  -H "Origin: https://www.sena-digital.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type"
```

**Expected Headers:**
```
HTTP/2 204
access-control-allow-origin: https://www.sena-digital.com
access-control-allow-methods: GET, POST, PUT, DELETE, OPTIONS
access-control-allow-headers: Content-Type, Authorization, X-Requested-With
access-control-allow-credentials: true
access-control-max-age: 86400
```

Test actual POST:
```bash
curl -X POST https://cloud-api.sena-digital.com/api/v1/login \
  -H "Origin: https://www.sena-digital.com" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@gmail.com","password":"12345678"}'
```

---

## Troubleshooting

### Jika Masih CORS Error:

**1. Check apakah .env sudah benar:**
```bash
cat .env | grep -E "SESSION_DOMAIN|SESSION_SAME_SITE|SANCTUM_STATEFUL_DOMAINS"
```

**2. Check apakah config sudah ter-cache:**
```bash
php artisan config:show session.domain
php artisan config:show session.same_site
php artisan config:show sanctum.stateful
```

**3. Check browser console network tab:**
- Lihat response headers dari OPTIONS request
- Pastikan ada `access-control-allow-origin: https://www.sena-digital.com`

**4. Clear browser cache dan cookies:**
- Old cookies bisa menyebabkan conflict
- Buka DevTools > Application > Clear storage

### Jika "422 CSRF Token Mismatch":

Frontend perlu request CSRF cookie terlebih dahulu:
```javascript
// Sebelum login, request CSRF cookie
await axios.get('https://cloud-api.sena-digital.com/sanctum/csrf-cookie', {
  withCredentials: true
});

// Kemudian baru login
await axios.post('https://cloud-api.sena-digital.com/api/v1/login', {
  email: 'admin@gmail.com',
  password: '12345678'
}, {
  withCredentials: true
});
```

---

## Perubahan File:

1. **config/cors.php**
   - Changed `max_age` from 0 to 86400 (cache preflight 24 hours)

2. **config/sanctum.php**
   - Added production domains to stateful domains list

3. **config/session.php**
   - Changed `same_site` from 'lax' to env variable (default 'none')

4. **Production .env** (NEW variables)
   - `SESSION_DOMAIN=.sena-digital.com`
   - `SESSION_SECURE_COOKIE=true`
   - `SESSION_SAME_SITE=none`
   - `SANCTUM_STATEFUL_DOMAINS=www.sena-digital.com,sena-digital.com,cloud-api.sena-digital.com`

---

## Important Notes:

⚠️ **same_site=none** requires **HTTPS** (SESSION_SECURE_COOKIE=true)
⚠️ **SESSION_DOMAIN** must start with dot (.) for subdomain sharing
⚠️ Always clear config cache after .env changes
⚠️ Browser may cache old CORS headers - test in incognito mode

---

**Status:** Ready to deploy
**Test after deploy:** Login from https://www.sena-digital.com/login
