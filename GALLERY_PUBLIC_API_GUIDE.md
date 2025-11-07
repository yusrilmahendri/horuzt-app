# Gallery Public API Documentation

## Overview

API untuk menampilkan foto galeri undangan pengantin secara public tanpa autentikasi.

---

## Endpoint Details

### Public Gallery List

**URL:** `GET /api/v1/galery/public`

**Authentication:** None (Public API)

**Purpose:** Mendapatkan daftar foto galeri untuk ditampilkan di halaman undangan.

---

## Request Parameters

### Query Parameters (Required)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | integer | Yes | ID user pemilik undangan |

### Query Parameters (Optional)

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `status` | integer | No | all | Filter by status (0 = inactive, 1 = active) |
| `per_page` | integer | No | 10 | Jumlah data per halaman (max: 100) |
| `page` | integer | No | 1 | Nomor halaman |

---

## Request Examples

### Basic Request

```bash
GET /api/v1/galery/public?user_id=123
```

### With Status Filter

```bash
GET /api/v1/galery/public?user_id=123&status=1
```

### With Pagination

```bash
GET /api/v1/galery/public?user_id=123&per_page=20&page=2
```

### cURL Example

```bash
curl -X GET "https://your-domain.com/api/v1/galery/public?user_id=123&status=1" \
  -H "Accept: application/json"
```

### JavaScript/Fetch Example

```javascript
const userId = 123;
const status = 1;

fetch(`https://your-domain.com/api/v1/galery/public?user_id=${userId}&status=${status}`, {
  method: 'GET',
  headers: {
    'Accept': 'application/json'
  }
})
  .then(response => response.json())
  .then(data => {
    console.log(data);
  });
```

### Axios Example

```javascript
import axios from 'axios';

const getGallery = async (userId, status = 1) => {
  try {
    const response = await axios.get('/api/v1/galery/public', {
      params: {
        user_id: userId,
        status: status,
        per_page: 20
      }
    });
    return response.data;
  } catch (error) {
    console.error('Error fetching gallery:', error);
  }
};

// Usage
getGallery(123, 1);
```

---

## Response Format

### Success Response (200 OK)

```json
{
  "message": "Data galery berhasil diambil.",
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "photo": "photos/abc123.jpg",
      "photo_url": "https://your-domain.com/storage/photos/abc123.jpg",
      "url_video": "https://youtube.com/watch?v=abc123",
      "nama_foto": "Pre-wedding Photo 1",
      "status": 1,
      "created_at": "2025-11-07T10:30:00.000000Z",
      "updated_at": "2025-11-07T10:30:00.000000Z"
    },
    {
      "id": 2,
      "user_id": 123,
      "photo": "photos/def456.jpg",
      "photo_url": "https://your-domain.com/storage/photos/def456.jpg",
      "url_video": null,
      "nama_foto": "Pre-wedding Photo 2",
      "status": 1,
      "created_at": "2025-11-07T11:00:00.000000Z",
      "updated_at": "2025-11-07T11:00:00.000000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 25,
    "from": 1,
    "to": 10
  }
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | ID galeri |
| `user_id` | integer | ID pemilik galeri |
| `photo` | string | Path file foto di storage |
| `photo_url` | string | URL lengkap foto (siap pakai) |
| `url_video` | string/null | Link video YouTube (optional) |
| `nama_foto` | string | Nama/caption foto |
| `status` | integer | Status aktif (1) atau tidak (0) |
| `created_at` | timestamp | Waktu upload |
| `updated_at` | timestamp | Waktu update terakhir |

---

## Error Responses

### Missing user_id (422 Unprocessable Entity)

```json
{
  "message": "The user id field is required.",
  "errors": {
    "user_id": [
      "The user id field is required."
    ]
  }
}
```

### Invalid user_id (422 Unprocessable Entity)

```json
{
  "message": "The selected user id is invalid.",
  "errors": {
    "user_id": [
      "The selected user id is invalid."
    ]
  }
}
```

### Invalid status value (422 Unprocessable Entity)

```json
{
  "message": "The selected status is invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```

---

## Use Cases

### 1. Halaman Undangan Pengantin

Menampilkan galeri foto pre-wedding atau foto couple di halaman undangan:

```html
<div id="gallery-container"></div>

<script>
async function loadGallery() {
  const userId = 123; // Dari URL atau data undangan
  
  try {
    const response = await fetch(`/api/v1/galery/public?user_id=${userId}&status=1`);
    const data = await response.json();
    
    const container = document.getElementById('gallery-container');
    
    data.data.forEach(photo => {
      const img = document.createElement('img');
      img.src = photo.photo_url;
      img.alt = photo.nama_foto;
      container.appendChild(img);
    });
  } catch (error) {
    console.error('Failed to load gallery:', error);
  }
}

loadGallery();
</script>
```

### 2. Gallery Slider/Carousel

Untuk implementasi slider foto:

```javascript
const loadGallerySlider = async (userId) => {
  const response = await fetch(`/api/v1/galery/public?user_id=${userId}&status=1&per_page=50`);
  const data = await response.json();
  
  // Initialize Swiper/Slick slider
  const slides = data.data.map(photo => ({
    image: photo.photo_url,
    title: photo.nama_foto,
    video: photo.url_video
  }));
  
  // Use your slider library here
  initializeSlider(slides);
};
```

### 3. Lazy Loading dengan Pagination

```javascript
let currentPage = 1;

const loadMorePhotos = async (userId) => {
  const response = await fetch(
    `/api/v1/galery/public?user_id=${userId}&status=1&per_page=12&page=${currentPage}`
  );
  const data = await response.json();
  
  // Render photos
  renderPhotos(data.data);
  
  // Check if more pages available
  if (data.pagination.current_page < data.pagination.last_page) {
    currentPage++;
    // Show "Load More" button
  }
};
```

---

## Comparison: Public vs Authenticated API

### Public API (New)

**Endpoint:** `GET /api/v1/galery/public`

- No authentication required
- Requires `user_id` as query parameter
- Public access for wedding invitation display
- Read-only access

**Request:**
```bash
GET /api/v1/galery/public?user_id=123
```

### Authenticated API (Existing)

**Endpoint:** `GET /api/v1/user/list-galery`

- Requires bearer token authentication
- Uses authenticated user's ID automatically
- For user dashboard/management
- Full CRUD access

**Request:**
```bash
GET /api/v1/user/list-galery
Authorization: Bearer {token}
```

---

## Performance Considerations

### Caching Strategy

Implement caching di frontend untuk mengurangi API calls:

```javascript
const galleryCache = new Map();

const getGallery = async (userId) => {
  const cacheKey = `gallery_${userId}`;
  
  // Check cache first
  if (galleryCache.has(cacheKey)) {
    const cached = galleryCache.get(cacheKey);
    const cacheAge = Date.now() - cached.timestamp;
    
    // Cache valid for 5 minutes
    if (cacheAge < 5 * 60 * 1000) {
      return cached.data;
    }
  }
  
  // Fetch from API
  const response = await fetch(`/api/v1/galery/public?user_id=${userId}`);
  const data = await response.json();
  
  // Store in cache
  galleryCache.set(cacheKey, {
    data: data,
    timestamp: Date.now()
  });
  
  return data;
};
```

### Image Optimization

Pertimbangkan untuk:
1. Resize images di backend sebelum upload
2. Serve WebP format untuk browser modern
3. Implement lazy loading untuk gambar
4. Use CDN untuk serve static files

---

## Security Considerations

### Rate Limiting

Meskipun public API, tetap implementasi rate limiting:

```php
Route::get('/v1/galery/public', 'publicIndex')
    ->middleware('throttle:60,1'); // 60 requests per minute
```

### Data Validation

API sudah validasi:
- `user_id` harus exist di database
- `status` hanya accept 0 atau 1
- `per_page` max 100 untuk prevent performance issues

### Privacy

Hanya foto dengan `status = 1` yang seharusnya ditampilkan public. User control privacy dari dashboard mereka.

---

## Testing

### Test dengan cURL

```bash
# Test valid request
curl -X GET "http://localhost:8000/api/v1/galery/public?user_id=1" \
  -H "Accept: application/json"

# Test invalid user_id
curl -X GET "http://localhost:8000/api/v1/galery/public?user_id=99999" \
  -H "Accept: application/json"

# Test missing user_id
curl -X GET "http://localhost:8000/api/v1/galery/public" \
  -H "Accept: application/json"

# Test with filters
curl -X GET "http://localhost:8000/api/v1/galery/public?user_id=1&status=1&per_page=5" \
  -H "Accept: application/json"
```

### Postman Collection

Import collection ini ke Postman:

```json
{
  "info": {
    "name": "Gallery Public API",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "item": [
    {
      "name": "Get Public Gallery",
      "request": {
        "method": "GET",
        "header": [],
        "url": {
          "raw": "{{base_url}}/api/v1/galery/public?user_id=1&status=1",
          "host": ["{{base_url}}"],
          "path": ["api", "v1", "galery", "public"],
          "query": [
            {"key": "user_id", "value": "1"},
            {"key": "status", "value": "1"}
          ]
        }
      }
    }
  ]
}
```

---

## Migration from Old Endpoint

Jika sebelumnya menggunakan authenticated endpoint:

### Before (Authenticated)
```javascript
// Required authentication
const response = await fetch('/api/v1/user/list-galery', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

### After (Public)
```javascript
// No authentication needed, but need user_id
const userId = 123; // From invitation data
const response = await fetch(`/api/v1/galery/public?user_id=${userId}`);
```

---

## Troubleshooting

### Photos not showing

1. Check storage link:
   ```bash
   php artisan storage:link
   ```

2. Verify file permissions:
   ```bash
   chmod -R 755 storage/app/public
   ```

3. Check APP_URL in .env:
   ```env
   APP_URL=https://your-domain.com
   ```

### Empty response

1. Verify user_id exists in database
2. Check if user has any photos with status = 1
3. Check Laravel logs: `tail -f storage/logs/laravel.log`

### Slow performance

1. Add database index on user_id:
   ```sql
   ALTER TABLE galeries ADD INDEX idx_user_id (user_id);
   ```

2. Implement caching in controller
3. Use CDN for images

---

## Summary

API `GET /api/v1/galery/public` adalah public endpoint untuk menampilkan galeri foto undangan pengantin tanpa autentikasi.

Key Points:
- No bearer token required
- Mandatory `user_id` query parameter
- Same response structure as authenticated endpoint
- Suitable for public wedding invitation pages
- Supports pagination and filtering
- Validates user_id exists in database

Endpoint ini melengkapi authenticated endpoint `/api/v1/user/list-galery` untuk kebutuhan yang berbeda:
- Public API: Untuk display di halaman undangan
- Authenticated API: Untuk user dashboard dan management
