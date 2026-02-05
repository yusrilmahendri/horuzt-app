# Komentar API Contract

API untuk mengelola komentar pada undangan pernikahan digital.

## 🔓 Public API
**Semua endpoint adalah PUBLIC** - tidak memerlukan authentication/token.
Tamu undangan dapat langsung submit komentar tanpa login.

---

## Table of Contents

1. [Quick Start](#quick-start) - 5-minute integration guide
2. [Endpoints](#endpoints)
   - [Create Komentar](#1-create-komentar) - POST /komentars
   - [Get Komentars (List)](#2-get-komentars-list) - GET /komentars
   - [Get Komentar Statistics](#3-get-komentar-statistics) - GET /komentars/statistics
3. [Business Rules](#business-rules)
4. [HTTP Status Codes](#http-status-codes)
5. [Sample cURL Commands](#sample-curl-commands)
6. [Frontend Integration Guide](#frontend-integration-guide)
   - [JavaScript/Fetch Example](#javascriptfetch-example)
   - [React/Next.js Example](#reactnextjs-example)
7. [Parameter Selection Guide](#parameter-selection-guide) - domain vs user_id
8. [Common Error Handling](#common-error-handling)
9. [Security & Best Practices](#security)

---

## Base URL
```
http://localhost:8000/api/v1
```

---

## Quick Start

### 5-Minute Integration

**Step 1: Store user_id in localStorage** (Frontend)
```javascript
// After wedding owner creates invitation
localStorage.setItem('wedding_user_id', '1');
```

**Step 2: Submit Komentar**
```javascript
const userId = localStorage.getItem('wedding_user_id');

fetch('http://localhost:8000/api/v1/komentars', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    user_id: parseInt(userId),
    nama: 'John Doe',
    komentar: 'Selamat ya! Bahagia selalu!'
  })
})
.then(res => res.json())
.then(data => console.log('Komentar disimpan:', data.data));
```

**Step 3: Load Komentars**
```javascript
fetch(`http://localhost:8000/api/v1/komentars?user_id=${userId}&per_page=10`)
  .then(res => res.json())
  .then(data => {
    console.log('Komentars:', data.data);
    console.log('Total:', data.meta.total);
  });
```

**That's it!** ✅ No authentication needed, no complex setup.

---

## Endpoints

### 1. Create Komentar

**Endpoint:** `POST /komentars`

**Payload (menggunakan domain):**
```json
{
  "domain": "test-wedding-696382d1b2753",
  "nama": "John Doe",
  "komentar": "Selamat ya! Bahagia selalu!"
}
```

**Payload (menggunakan user_id dari localStorage):**
```json
{
  "user_id": 1,
  "nama": "John Doe",
  "komentar": "Selamat ya! Bahagia selalu!"
}
```

**Validation Rules:**
- `domain`: nullable, string, must exist in settings table (required if user_id not provided)
- `user_id`: nullable, integer, must exist in users table (required if domain not provided)
- `nama`: required, string, min 2 chars, max 255 chars
- `komentar`: required, string, min 5 chars, max 500 chars

**Note:** Either `domain` OR `user_id` must be provided (not both required, one is sufficient)

**Success Response (201):**
```json
{
  "message": "Komentar berhasil disimpan!",
  "data": {
    "id": 1,
    "nama": "John Doe",
    "komentar": "Selamat ya! Bahagia selalu!",
    "created_at": "2026-01-11 11:04:56"
  }
}
```

**Error Response (422 - Validation Error):**
```json
{
  "message": "Data yang dikirim tidak valid.",
  "errors": {
    "nama": ["Nama minimal 2 karakter."],
    "komentar": ["Komentar minimal 5 karakter."]
  }
}
```

**Error Response (429 - Rate Limit):**
```json
{
  "message": "Too many comments submitted. Please try again later (limit: 10 per hour)."
}
```

**Error Response (403 - Forbidden):**
```json
{
  "message": "Wedding invitation is not active."
}
```

**Error Response (422 - Missing Parameters):**
```json
{
  "message": "Domain atau user_id wajib diisi."
}
```

---

### 2. Get Komentars (List)

**Endpoint:** `GET /komentars`

**Query Parameters:**
- `domain` (nullable): Wedding domain (required if user_id not provided)
- `user_id` (nullable): User ID (required if domain not provided)
- `page` (optional): Page number, default 1
- `per_page` (optional): Items per page, default 20, max 100

**Example (menggunakan domain):**
```
GET /komentars?domain=test-wedding-696382d1b2753&per_page=10&page=1
```

**Example (menggunakan user_id):**
```
GET /komentars?user_id=1&per_page=10&page=1
```

**Success Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "nama": "John Doe",
      "komentar": "Selamat ya! Bahagia selalu!",
      "created_at": "2026-01-11 11:04:56"
    },
    {
      "id": 2,
      "nama": "Jane Smith",
      "komentar": "Congratulations!",
      "created_at": "2026-01-11 12:30:00"
    }
  ],
  "meta": {
    "total": 10,
    "current_page": 1,
    "per_page": 10,
    "last_page": 1
  }
}
```

**Error Response (404 - Not Found):**
```json
{
  "message": "Wedding not found for this domain."
}
```

**Error Response (422 - Validation Error):**
```json
{
  "message": "Data yang dikirim tidak valid.",
  "errors": {
    "domain": ["The domain field is required when user id is not present."]
  }
}
```

**Error Response (422 - Missing Parameters):**
```json
{
  "message": "Domain atau user_id wajib diisi."
}
```

---

### 3. Get Komentar Statistics

**Endpoint:** `GET /komentars/statistics`

**Query Parameters:**
- `domain` (nullable): Wedding domain (required if user_id not provided)
- `user_id` (nullable): User ID (required if domain not provided)

**Example (menggunakan domain):**
```
GET /komentars/statistics?domain=test-wedding-696382d1b2753
```

**Example (menggunakan user_id):**
```
GET /komentars/statistics?user_id=1
```

**Success Response (200):**
```json
{
  "data": {
    "domain": "test-wedding-696382d1b2753",
    "total_komentars": 10
  }
}
```

**Error Response (404 - Not Found):**
```json
{
  "message": "Wedding not found."
}
```

**Error Response (422 - Missing Parameters):**
```json
{
  "message": "Domain atau user_id wajib diisi."
}
```

---

## Business Rules

1. **Invitation Status**: Invitation harus berstatus `step3` (completed)
2. **Payment Status**: Mempelai harus memiliki `kd_status = 'SB'` (Sudah Bayar)
3. **Domain Expiry**: Domain undangan harus masih aktif (belum expired)
4. **Rate Limiting**: Maximum 10 komentar per jam per IP address
5. **XSS Protection**: HTML tags akan di-strip otomatis dari input

---

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success (GET) |
| 201 | Created (POST) |
| 422 | Validation Error |
| 403 | Forbidden (business rule violation) |
| 404 | Not Found |
| 429 | Too Many Requests (rate limit) |
| 500 | Internal Server Error |

---

## Sample cURL Commands

### Create Komentar (menggunakan domain)
```bash
curl -X POST http://localhost:8000/api/v1/komentars \
  -H "Content-Type: application/json" \
  -d '{
    "domain": "test-wedding-696382d1b2753",
    "nama": "John Doe",
    "komentar": "Selamat ya! Bahagia selalu!"
  }'
```

### Create Komentar (menggunakan user_id dari localStorage)
```bash
curl -X POST http://localhost:8000/api/v1/komentars \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "nama": "John Doe",
    "komentar": "Selamat ya! Bahagia selalu!"
  }'
```

### Get Komentars (menggunakan domain)
```bash
curl -X GET "http://localhost:8000/api/v1/komentars?domain=test-wedding-696382d1b2753&per_page=10"
```

### Get Komentars (menggunakan user_id)
```bash
curl -X GET "http://localhost:8000/api/v1/komentars?user_id=1&per_page=10"
```

### Get Statistics (menggunakan domain)
```bash
curl -X GET "http://localhost:8000/api/v1/komentars/statistics?domain=test-wedding-696382d1b2753"
```

### Get Statistics (menggunakan user_id)
```bash
curl -X GET "http://localhost:8000/api/v1/komentars/statistics?user_id=1"
```

---
