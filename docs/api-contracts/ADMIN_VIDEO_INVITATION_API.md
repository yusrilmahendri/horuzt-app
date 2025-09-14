# API Contract Documentation - Admin Video Invitation Categories

## Overview
This API contract covers the complete management of video invitation categories and their synchronized themes from the admin perspective. Each category automatically creates a corresponding theme with identical basic data (synchronization).

**Base URL**: `/api/admin/video-categories`  
**Authentication**: Required (Sanctum Token)  
**Authorization**: Admin role required  
**Content-Type**: `application/json` (except file uploads)

---

## Business Process Summary

1. **Synchronized Management**: Creating/updating/deleting a category automatically creates/updates/deletes its corresponding theme
2. **Simple Data Structure**: Only 4 core fields are managed: `nama_kategori`, `slug`, `image`, `is_active`
3. **Image Storage**: Images stored in `storage/app/public/video-categories/`
4. **Auto Slug Generation**: Slugs auto-generated from category name if not provided
5. **Type Filtering**: All operations filter by `type = 'video'`
6. **Video-Specific Features**: Categories support video-related metadata and preview handling

---

## Authentication

### Header Requirements
```
Authorization: Bearer {sanctum_token}
Accept: application/json
Content-Type: application/json
```

### Error Response (401 Unauthorized)
```json
{
    "message": "Unauthenticated."
}
```

### Error Response (403 Forbidden)
```json
{
    "message": "This action is unauthorized."
}
```

---

## API Endpoints

### 1. List Video Categories

**Endpoint**: `GET /api/admin/video-categories`

**Purpose**: Retrieve paginated list of video invitation categories

**Parameters**:
- `search` (string, optional): Search by category name
- `status` (string, optional): Filter by status (`active` or `inactive`)
- `per_page` (integer, optional): Items per page (default: 15, max: 100)
- `page` (integer, optional): Page number (default: 1)

**Request Example**:
```
GET /api/admin/video-categories?search=cinematic&status=active&per_page=10&page=1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "nama_kategori": "Cinematic",
            "slug": "cinematic",
            "image": "http://localhost/storage/video-categories/cinematic-1693847200.jpg",
            "is_active": true,
            "created_at": "2025-09-09T10:00:00.000000Z",
            "updated_at": "2025-09-09T10:00:00.000000Z"
        },
        {
            "id": 2,
            "nama_kategori": "Slideshow",
            "slug": "slideshow",
            "image": "http://localhost/storage/video-categories/slideshow-1693847300.jpg",
            "is_active": true,
            "created_at": "2025-09-09T10:01:00.000000Z",
            "updated_at": "2025-09-09T10:01:00.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "from": 1,
        "to": 2,
        "per_page": 10,
        "total": 2
    }
}
```

**Error Response (500 Internal Server Error)**:
```json
{
    "status": false,
    "message": "Failed to retrieve video categories."
}
```

---

### 2. Create Video Category

**Endpoint**: `POST /api/admin/video-categories`

**Purpose**: Create new video invitation category and its synchronized theme

**Content-Type**: `multipart/form-data`

**Request Fields**:
- `nama_kategori` (string, required): Category name (max: 255 characters)
- `slug` (string, optional): URL-friendly identifier (auto-generated if not provided)
- `image` (file, optional): Category preview image (jpeg, png, jpg, gif, max: 2MB)
- `is_active` (string/boolean, optional): Active status (accepts: "true", "false", "1", "0", true, false - default: true)

**Request Example**:
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Wedding Cinematic" \
  -F "slug=wedding-cinematic" \
  -F "image=@video-preview.jpg" \
  -F "is_active=true" \
  /api/admin/video-categories
```

**Success Response (201 Created)**:
```json
{
    "status": true,
    "message": "Video category and theme created successfully",
    "data": {
        "id": 3,
        "nama_kategori": "Wedding Cinematic",
        "slug": "wedding-cinematic",
        "image": "http://localhost/storage/video-categories/wedding-cinematic-1693847400.jpg",
        "is_active": true,
        "theme_id": 25
    }
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "nama_kategori": ["The nama kategori field is required."],
        "slug": ["The slug has already been taken."],
        "image": ["The image must be an image.", "The image may not be greater than 2048 kilobytes."]
    }
}
```

**Error Response (500 Internal Server Error)**:
```json
{
    "status": false,
    "message": "Failed to create video category."
}
```

---

### 3. Show Video Category

**Endpoint**: `GET /api/admin/video-categories/{id}`

**Purpose**: Retrieve specific video invitation category details

**Parameters**:
- `id` (integer, required): Category ID

**Request Example**:
```
GET /api/admin/video-categories/1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": {
        "id": 1,
        "nama_kategori": "Cinematic",
        "slug": "cinematic",
        "image": "http://localhost/storage/video-categories/cinematic-1693847200.jpg",
        "is_active": true,
        "created_at": "2025-09-09T10:00:00.000000Z",
        "updated_at": "2025-09-09T10:00:00.000000Z"
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Video category not found."
}
```

---

### 4. Update Video Category

**Endpoint**: `PUT /api/admin/video-categories/{id}`

**Purpose**: Update video invitation category and its synchronized theme

**Content-Type**: `multipart/form-data` (if uploading image) or `application/json`

**Parameters**:
- `id` (integer, required): Category ID

**Request Fields**:
- `nama_kategori` (string, required): Category name (max: 255 characters)
- `slug` (string, optional): URL-friendly identifier
- `image` (file, optional): New category preview image (jpeg, png, jpg, gif, max: 2MB)
- `is_active` (string/boolean, optional): Active status (accepts: "true", "false", "1", "0", true, false)

**Request Example (with image)**:
```bash
curl -X PUT \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Professional Cinematic" \
  -F "slug=professional-cinematic" \
  -F "image=@new-preview.jpg" \
  -F "is_active=true" \
  /api/admin/video-categories/1
```

**Request Example (JSON only)**:
```json
{
    "nama_kategori": "Professional Cinematic",
    "slug": "professional-cinematic",
    "is_active": true
}
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Video category and theme updated successfully",
    "data": {
        "id": 1,
        "nama_kategori": "Professional Cinematic",
        "slug": "professional-cinematic",
        "image": "http://localhost/storage/video-categories/professional-cinematic-1693847500.jpg",
        "is_active": true
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Video category not found."
}
```

**Validation Error Response (422 Unprocessable Entity)**:
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "nama_kategori": ["The nama kategori field is required."],
        "slug": ["The slug has already been taken."]
    }
}
```

---

### 5. Delete Video Category

**Endpoint**: `DELETE /api/admin/video-categories/{id}`

**Purpose**: Delete video invitation category and its synchronized theme

**⚠️ Warning**: This action also removes all associated video templates and user selections

**Parameters**:
- `id` (integer, required): Category ID

**Request Example**:
```
DELETE /api/admin/video-categories/1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Video category and theme deleted successfully"
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Video category not found."
}
```

---

### 6. Toggle Category Activation

**Endpoint**: `PATCH /api/admin/video-categories/{id}/toggle`

**Purpose**: Toggle activation status of video category and its synchronized theme

**Parameters**:
- `id` (integer, required): Category ID

**Request Body**:
```json
{
    "is_active": false
}
```

**Note**: For PATCH requests, `is_active` accepts boolean values (true/false).

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Video category deactivated successfully",
    "data": {
        "id": 1,
        "nama_kategori": "Cinematic",
        "slug": "cinematic",
        "image": "http://localhost/storage/video-categories/cinematic-1693847200.jpg",
        "is_active": false
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Video category not found."
}
```

---

### 7. Get Statistics

**Endpoint**: `GET /api/admin/video-categories/statistics`

**Purpose**: Retrieve comprehensive statistics about video invitation categories

**Request Example**:
```
GET /api/admin/video-categories/statistics
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": {
        "total_categories": 15,
        "active_categories": 12,
        "inactive_categories": 3,
        "categories_with_images": 10,
        "synchronized_themes": 15
    }
}
```

---

## Data Structures

### Video Category Object
```json
{
    "id": 1,
    "nama_kategori": "Cinematic",
    "slug": "cinematic",
    "image": "http://localhost/storage/video-categories/cinematic-1693847200.jpg",
    "is_active": true,
    "created_at": "2025-09-09T10:00:00.000000Z",
    "updated_at": "2025-09-09T10:00:00.000000Z"
}
```

### Meta Object (Pagination)
```json
{
    "current_page": 1,
    "from": 1,
    "to": 15,
    "per_page": 15,
    "total": 15
}
```

### Statistics Object
```json
{
    "total_categories": 15,
    "active_categories": 12,
    "inactive_categories": 3,
    "categories_with_images": 10,
    "synchronized_themes": 15
}
```

---

## Error Handling

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Internal Server Error

### Standard Error Response Format
```json
{
    "status": false,
    "message": "Error description"
}
```

### Validation Error Response Format
```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "field_name": ["Error message 1", "Error message 2"]
    }
}
```

---

## Video-Specific Considerations

### Image Preview Guidelines
- Images should represent video style/theme
- Recommended dimensions: 1920x1080 (16:9 aspect ratio)
- Preview images help users understand video output style
- Should showcase the category's visual aesthetic

### Video Category Types
Common video invitation categories include:
- **Cinematic**: Professional film-style videos
- **Slideshow**: Photo-based transition videos
- **Animation**: Animated graphics and effects
- **Minimalist**: Simple, clean video styles
- **Romantic**: Romance-themed video styles
- **Traditional**: Classic wedding video styles

---

## Frontend Implementation Examples

### 1. List Video Categories with Search
```javascript
async function getVideoCategories(page = 1, search = '', status = '') {
    const params = new URLSearchParams({
        page: page.toString(),
        per_page: '15'
    });
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    
    const response = await fetch(`/api/admin/video-categories?${params}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        }
    });
    
    if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    return await response.json();
}
```

### 2. Create Video Category with Preview Image
```javascript
async function createVideoCategory(formData) {
    try {
        const response = await fetch('/api/admin/video-categories', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
            },
            body: formData // FormData object with preview image
        });
        
        const result = await response.json();
        
        if (!result.status) {
            throw new Error(result.message || 'Failed to create video category');
        }
        
        return result;
    } catch (error) {
        console.error('Error creating video category:', error);
        throw error;
    }
}

// Usage example
const createCategoryForm = document.getElementById('createForm');
createCategoryForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(createCategoryForm);
    
    try {
        const result = await createVideoCategory(formData);
        alert(result.message);
        // Refresh category list
        loadVideoCategories();
    } catch (error) {
        alert('Error: ' + error.message);
    }
});
```

### 3. Update Video Category
```javascript
async function updateVideoCategory(id, data) {
    const response = await fetch(`/api/admin/video-categories/${id}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
    
    const result = await response.json();
    
    if (!result.status) {
        throw new Error(result.message || 'Failed to update video category');
    }
    
    return result;
}
```

### 4. Toggle Video Category Activation
```javascript
async function toggleVideoActivation(id, isActive) {
    try {
        const response = await fetch(`/api/admin/video-categories/${id}/toggle`, {
            method: 'PATCH',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ is_active: isActive })
        });
        
        const result = await response.json();
        
        if (!result.status) {
            throw new Error(result.message);
        }
        
        return result;
    } catch (error) {
        console.error('Error toggling activation:', error);
        throw error;
    }
}
```

### 5. Delete Video Category with Confirmation
```javascript
async function deleteVideoCategory(id) {
    if (!confirm('Are you sure you want to delete this video category? This will also remove all associated themes and user selections.')) {
        return;
    }
    
    try {
        const response = await fetch(`/api/admin/video-categories/${id}`, {
            method: 'DELETE',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (!result.status) {
            throw new Error(result.message);
        }
        
        alert(result.message);
        // Refresh category list
        loadVideoCategories();
        
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
```

### 6. Get Video Categories Statistics Dashboard
```javascript
async function loadVideoCategoryStatistics() {
    try {
        const response = await fetch('/api/admin/video-categories/statistics', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (result.status) {
            updateStatsDashboard(result.data);
        }
        
    } catch (error) {
        console.error('Error loading statistics:', error);
    }
}

function updateStatsDashboard(stats) {
    document.getElementById('total-categories').textContent = stats.total_categories;
    document.getElementById('active-categories').textContent = stats.active_categories;
    document.getElementById('inactive-categories').textContent = stats.inactive_categories;
    document.getElementById('categories-with-images').textContent = stats.categories_with_images;
    document.getElementById('synchronized-themes').textContent = stats.synchronized_themes;
}
```

---

## File Upload Guidelines

### Video Preview Image Specifications
- **Accepted formats**: JPEG, PNG, JPG, GIF
- **Maximum size**: 2MB (2048 KB)
- **Recommended dimensions**: 1920x1080 (16:9 aspect ratio)
- **Storage path**: `storage/app/public/video-categories/`
- **Naming convention**: `{slug}-{timestamp}.{extension}`
- **URL access**: `/storage/video-categories/{filename}`

### Frontend Image Validation
```javascript
function validateVideoPreviewImage(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB
    const recommendedRatio = 16/9; // 16:9 aspect ratio
    
    // Check file type
    if (!allowedTypes.includes(file.type)) {
        throw new Error('Invalid file type. Please upload JPEG, PNG, JPG, or GIF.');
    }
    
    // Check file size
    if (file.size > maxSize) {
        throw new Error('File size too large. Maximum size is 2MB.');
    }
    
    // Check image dimensions (optional)
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = function() {
            const ratio = this.width / this.height;
            if (Math.abs(ratio - recommendedRatio) > 0.1) {
                console.warn(`Image aspect ratio is ${ratio.toFixed(2)}:1, recommended is 16:9 (1.78:1)`);
            }
            resolve(true);
        };
        img.onerror = () => reject(new Error('Invalid image file'));
        img.src = URL.createObjectURL(file);
    });
}

### Form Data Handling for Video Categories
```javascript
// For multipart/form-data requests (with file uploads)
function createVideoCategoryFormData(data) {
    const formData = new FormData();
    formData.append('nama_kategori', data.nama_kategori);
    
    if (data.slug) formData.append('slug', data.slug);
    if (data.image) formData.append('image', data.image);
    
    // Critical: Send boolean as string for form-data
    if (data.is_active !== undefined) {
        formData.append('is_active', data.is_active ? 'true' : 'false');
    }
    
    return formData;
}

// For JSON requests (without file uploads)
function createVideoCategoryJSON(data) {
    return {
        nama_kategori: data.nama_kategori,
        slug: data.slug || null,
        is_active: data.is_active !== undefined ? Boolean(data.is_active) : true
    };
}

// Example usage with form validation
async function handleVideoFormSubmission(formElement) {
    const formData = new FormData(formElement);
    
    // Ensure is_active is properly formatted
    const isActive = formData.get('is_active');
    if (isActive) {
        formData.set('is_active', isActive === 'on' || isActive === 'true' ? 'true' : 'false');
    }
    
    try {
        const result = await createVideoCategory(formData);
        console.log('Success:', result);
    } catch (error) {
        console.error('Error:', error);
    }
}
```
```

---

## Performance Optimization

### Caching Strategy
- **List endpoint**: Cache for 5 minutes
- **Individual categories**: Cache for 15 minutes
- **Statistics**: Cache for 30 minutes
- **Clear cache**: On any write operation (create/update/delete)

### Image Optimization
```javascript
// Client-side image compression before upload
async function compressImage(file, maxWidth = 1920, quality = 0.8) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        
        img.onload = function() {
            const ratio = Math.min(maxWidth / this.width, maxWidth / this.height);
            canvas.width = this.width * ratio;
            canvas.height = this.height * ratio;
            
            ctx.drawImage(this, 0, 0, canvas.width, canvas.height);
            
            canvas.toBlob(resolve, file.type, quality);
        };
        
        img.src = URL.createObjectURL(file);
    });
}
```

### Pagination Performance
```javascript
// Implement efficient pagination
class VideoCategoryPaginator {
    constructor(apiBaseUrl, authToken) {
        this.apiBaseUrl = apiBaseUrl;
        this.authToken = authToken;
        this.cache = new Map();
    }
    
    async getPage(page, filters = {}) {
        const cacheKey = this.getCacheKey(page, filters);
        
        if (this.cache.has(cacheKey)) {
            return this.cache.get(cacheKey);
        }
        
        const params = new URLSearchParams({
            page: page.toString(),
            per_page: '15',
            ...filters
        });
        
        const response = await fetch(`${this.apiBaseUrl}?${params}`, {
            headers: {
                'Authorization': `Bearer ${this.authToken}`,
                'Accept': 'application/json'
            }
        });
        
        const result = await response.json();
        
        // Cache successful responses for 5 minutes
        if (result.status) {
            setTimeout(() => this.cache.delete(cacheKey), 5 * 60 * 1000);
            this.cache.set(cacheKey, result);
        }
        
        return result;
    }
    
    getCacheKey(page, filters) {
        return `page-${page}-${JSON.stringify(filters)}`;
    }
    
    clearCache() {
        this.cache.clear();
    }
}
```

---

## Security Considerations

### Input Validation
- All input fields are validated and sanitized
- File uploads restricted to images only
- Slug uniqueness enforced across all categories
- XSS protection implemented on all text fields

### Authorization & Access Control
- Admin role required for all operations
- Rate limiting: 100 requests per minute per user
- CSRF protection enabled for state-changing operations
- SQL injection protection via Eloquent ORM

### File Security
- Uploaded files stored outside web root
- File type validation using MIME type checking
- Virus scanning recommended for production
- Image metadata stripping for privacy

---

## Testing & Quality Assurance

### API Testing Examples
```bash
# Test video category creation
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Test Video Category" \
  -F "slug=test-video" \
  -F "is_active=true" \
  /api/admin/video-categories

# Test category listing with filters
curl -H "Authorization: Bearer {token}" \
  "/api/admin/video-categories?search=cinematic&status=active"

# Test category update
curl -X PUT \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"nama_kategori":"Updated Video Category","is_active":false}' \
  /api/admin/video-categories/1

# Test category deletion
curl -X DELETE \
  -H "Authorization: Bearer {token}" \
  /api/admin/video-categories/1
```

### Frontend Testing Checklist
- [ ] Category creation with and without images
- [ ] Category listing with pagination
- [ ] Search functionality
- [ ] Status filtering (active/inactive)
- [ ] Category update operations
- [ ] Activation/deactivation toggle
- [ ] Category deletion with confirmation
- [ ] Statistics dashboard loading
- [ ] Error handling for all scenarios
- [ ] File upload validation
- [ ] Image preview functionality
- [ ] Responsive design testing

---

## Troubleshooting

### Common Issues

1. **Image Upload Fails**
   - Check file size (max 2MB)
   - Verify file type (JPEG, PNG, JPG, GIF only)
   - Ensure storage directory is writable
   - Check PHP upload limits

2. **Slug Conflicts**
   - Slugs must be unique across all categories
   - Auto-generation adds counter for duplicates
   - Manual slugs are validated for uniqueness

3. **Synchronization Issues**
   - Category and theme are created/updated in database transaction
   - If theme creation fails, category creation is rolled back
   - Check database logs for transaction errors

4. **Permission Errors**
   - Ensure user has admin role
   - Verify Sanctum token is valid
   - Check middleware configuration

### Debug Mode Response Format
```json
{
    "status": false,
    "message": "Failed to create video category.",
    "debug": {
        "error": "Database transaction failed",
        "file": "/path/to/controller.php",
        "line": 123,
        "trace": "..."
    }
}
```

This comprehensive API contract documentation provides all necessary information for frontend developers to implement video invitation category management with complete functionality and error handling.
