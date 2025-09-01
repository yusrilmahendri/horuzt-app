# Testing Profile Endpoints - Fixed

## MASALAH YANG DIPERBAIKI ✅
- **Route Conflict**: Route `/api/profile` terdefinisi 2 kali (admin & user)
- **Solution**: Admin endpoints sekarang menggunakan prefix `/api/admin/profile`

## ENDPOINTS SEKARANG:

### 🔵 USER PROFILE ENDPOINTS
**Base URL**: `/api/profile`
**Middleware**: `['auth:sanctum', 'role:user']`

### 🔴 ADMIN PROFILE ENDPOINTS  
**Base URL**: `/api/admin/profile`
**Middleware**: `['auth:sanctum', 'role:admin']`

---

## 1. LOGIN CREDENTIALS

### Admin User:
- Email: `admin@gmail.com`
- Password: `12345678`
- Role: `admin`

### Regular User:
- Email: `hanif@gmail.com`  
- Password: `12345678`
- Role: `user`

---

## 2. TESTING USER PROFILE

### Login sebagai User
```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "hanif@gmail.com",
    "password": "12345678"
  }'
```

### Get User Profile
```bash
curl -X GET http://127.0.0.1:8000/api/profile \
  -H "Authorization: Bearer USER_TOKEN_HERE" \
  -H "Accept: application/json"
```

### Update User Profile  
```bash
curl -X PUT http://127.0.0.1:8000/api/profile \
  -H "Authorization: Bearer USER_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Hanif Updated",
    "phone": "081234567890"
  }'
```

---

## 3. TESTING ADMIN PROFILE

### Login sebagai Admin
```bash
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@gmail.com",
    "password": "12345678"
  }'
```

### Get Admin Profile
```bash
curl -X GET http://127.0.0.1:8000/api/admin/profile \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -H "Accept: application/json"
```

### Update Admin Profile  
```bash
curl -X PUT http://127.0.0.1:8000/api/admin/profile \
  -H "Authorization: Bearer ADMIN_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Admin Updated",
    "phone": "081234567890"
  }'
```

---

## 4. COMPLETE ENDPOINT LIST

### User Profile (Role: user)
- `GET /api/profile` - profile.show
- `PUT /api/profile` - profile.update  
- `POST /api/profile/photo` - profile.upload-photo
- `DELETE /api/profile/photo` - profile.delete-photo
- `POST /api/profile/change-password` - profile.change-password

### Admin Profile (Role: admin)
- `GET /api/admin/profile` - admin.profile.show
- `PUT /api/admin/profile` - admin.profile.update
- `POST /api/admin/profile/photo` - admin.profile.upload-photo
- `DELETE /api/admin/profile/photo` - admin.profile.delete-photo
- `POST /api/admin/profile/change-password` - admin.profile.change-password

---

## 5. ERROR CODES

- **401 Unauthorized**: Token tidak valid atau tidak ada
- **403 Forbidden**: User tidak memiliki role yang sesuai
- **422 Validation Error**: Data input tidak valid
- **404 Not Found**: Endpoint tidak ditemukan

---

## 6. FRONTEND INTEGRATION

```javascript
// User Profile
const userProfileResponse = await fetch('/api/profile', {
  headers: {
    'Authorization': `Bearer ${userToken}`,
    'Accept': 'application/json'
  }
});

// Admin Profile  
const adminProfileResponse = await fetch('/api/admin/profile', {
  headers: {
    'Authorization': `Bearer ${adminToken}`,
    'Accept': 'application/json'
  }
});
```
