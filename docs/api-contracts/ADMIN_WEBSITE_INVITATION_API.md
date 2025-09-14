# API Contract Documentation - Admin Website Invitation Categories

## Overview
This API contract covers the complete management of website invitation categories and their synchronized themes from the admin perspective. Each category automatically creates a corresponding theme with identical basic data (synchronization).

**Base URL**: `/api/admin/website-categories`  
**Authentication**: Required (Sanctum Token)  
**Authorization**: Admin role required  
**Content-Type**: `application/json` (except file uploads)

---

## Business Process Summary

1. **Synchronized Management**: Creating/updating/deleting a category automatically creates/updates/deletes its corresponding theme
2. **Simple Data Structure**: Only 4 core fields are managed: `nama_kategori`, `slug`, `image`, `is_active`
3. **Image Storage**: Images stored in `storage/app/public/website-categories/`
4. **Auto Slug Generation**: Slugs auto-generated from category name if not provided
5. **Type Filtering**: All operations filter by `type = 'website'`

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

### 1. List Website Categories

**Endpoint**: `GET /api/admin/website-categories`

**Purpose**: Retrieve paginated list of website invitation categories

**Parameters**:
- `search` (string, optional): Search by category name
- `status` (string, optional): Filter by status (`active` or `inactive`)
- `per_page` (integer, optional): Items per page (default: 15, max: 100)
- `page` (integer, optional): Page number (default: 1)

**Request Example**:
```
GET /api/admin/website-categories?search=mobile&status=active&per_page=10&page=1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": [
        {
            "id": 1,
            "nama_kategori": "Mobile",
            "slug": "mobile",
            "image": "website-categories/mobile-1693847200.jpg",
            "is_active": true,
            "created_at": "2025-09-09T10:00:00.000000Z",
            "updated_at": "2025-09-09T10:00:00.000000Z"
        },
        {
            "id": 2,
            "nama_kategori": "Parallax",
            "slug": "parallax",
            "image": "website-categories/parallax-1693847300.jpg",
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
    "message": "Failed to retrieve website categories."
}
```

---

### 2. Create Website Category

**Endpoint**: `POST /api/admin/website-categories`

**Purpose**: Create new website invitation category and its synchronized theme

**Content-Type**: `multipart/form-data`

**Request Fields**:
- `nama_kategori` (string, required): Category name (max: 255 characters)
- `slug` (string, optional): URL-friendly identifier (auto-generated if not provided)
- `image` (file, optional): Category image (jpeg, png, jpg, gif, max: 2MB)
- `is_active` (string/boolean, optional): Active status (accepts: "true", "false", "1", "0", true, false - default: true)

**Request Example**:
```bash
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Scroll Modern" \
  -F "slug=scroll-modern" \
  -F "image=@category-image.jpg" \
  -F "is_active=true" \
  /api/admin/website-categories
```

**Success Response (201 Created)**:
```json
{
    "status": true,
    "message": "Website category and theme created successfully",
    "data": {
        "id": 3,
        "nama_kategori": "Scroll Modern",
        "slug": "scroll-modern",
        "image": "website-categories/scroll-modern-1693847400.jpg",
        "is_active": true,
        "theme_id": 15
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
    "message": "Failed to create website category."
}
```

---

### 3. Show Website Category

**Endpoint**: `GET /api/admin/website-categories/{id}`

**Purpose**: Retrieve specific website invitation category details

**Parameters**:
- `id` (integer, required): Category ID

**Request Example**:
```
GET /api/admin/website-categories/1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": {
        "id": 1,
        "nama_kategori": "Mobile",
        "slug": "mobile",
        "image": "website-categories/mobile-1693847200.jpg",
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
    "message": "Website category not found."
}
```

---

### 4. Update Website Category

**Endpoint**: `PUT /api/admin/website-categories/{id}`

**Purpose**: Update website invitation category and its synchronized theme

**Content-Type**: `multipart/form-data` (if uploading image) or `application/json`

**Parameters**:
- `id` (integer, required): Category ID

**Request Fields**:
- `nama_kategori` (string, required): Category name (max: 255 characters)
- `slug` (string, optional): URL-friendly identifier
- `image` (file, optional): New category image (jpeg, png, jpg, gif, max: 2MB)
- `is_active` (string/boolean, optional): Active status (accepts: "true", "false", "1", "0", true, false)

**Request Example (with image)**:
```bash
curl -X PUT \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Mobile Responsive" \
  -F "slug=mobile-responsive" \
  -F "image=@new-image.jpg" \
  -F "is_active=true" \
  /api/admin/website-categories/1
```

**Request Example (JSON only)**:
```json
{
    "nama_kategori": "Mobile Responsive",
    "slug": "mobile-responsive",
    "is_active": true
}
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Website category and theme updated successfully",
    "data": {
        "id": 1,
        "nama_kategori": "Mobile Responsive",
        "slug": "mobile-responsive",
        "image": "website-categories/mobile-responsive-1693847500.jpg",
        "is_active": true
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Website category not found."
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

### 5. Delete Website Category

**Endpoint**: `DELETE /api/admin/website-categories/{id}`

**Purpose**: Delete website invitation category and its synchronized theme

**Parameters**:
- `id` (integer, required): Category ID

**Request Example**:
```
DELETE /api/admin/website-categories/1
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Website category and theme deleted successfully"
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Website category not found."
}
```

---

### 6. Toggle Category Activation

**Endpoint**: `PATCH /api/admin/website-categories/{id}/toggle`

**Purpose**: Toggle activation status of category and its synchronized theme

**Parameters**:
- `id` (integer, required): Category ID

**Request Body**:
```json
{
    "is_active": false
}
```

**Note**: For PATCH requests, `is_active` accepts boolean values (true/false) or string values ("true"/"false", "1"/"0").

**Success Response (200 OK)**:
```json
{
    "status": true,
    "message": "Website category deactivated successfully",
    "data": {
        "id": 1,
        "nama_kategori": "Mobile",
        "slug": "mobile",
        "image": "website-categories/mobile-1693847200.jpg",
        "is_active": false
    }
}
```

**Error Response (404 Not Found)**:
```json
{
    "status": false,
    "message": "Website category not found."
}
```

---

### 7. Get Statistics

**Endpoint**: `GET /api/admin/website-categories/statistics`

**Purpose**: Retrieve statistics about website invitation categories

**Request Example**:
```
GET /api/admin/website-categories/statistics
```

**Success Response (200 OK)**:
```json
{
    "status": true,
    "data": {
        "total_categories": 25,
        "active_categories": 20,
        "inactive_categories": 5,
        "categories_with_images": 18,
        "synchronized_themes": 25
    }
}
```

---

## Data Structures

### Category Object
```json
{
    "id": 1,
    "nama_kategori": "Mobile",
    "slug": "mobile",
    "image": "website-categories/mobile-1693847200.jpg",
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
    "total": 25
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

## Frontend Implementation Examples

### 1. List Categories with Search and Pagination
```javascript
async function getWebsiteCategories(page = 1, search = '', status = '') {
    const params = new URLSearchParams({
        page: page.toString(),
        per_page: '15'
    });
    
    if (search) params.append('search', search);
    if (status) params.append('status', status);
    
    const response = await fetch(`/api/admin/website-categories?${params}`, {
        headers: {
            'Authorization': `Bearer ${token}`,
            'Accept': 'application/json'
        }
    });
    
    return await response.json();
}
```

### 2. Create Category with Image Upload
```javascript
async function createWebsiteCategory(formData) {
    const response = await fetch('/api/admin/website-categories', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${token}`,
        },
        body: formData // FormData object with image
    });
    
    return await response.json();
}
```

### 3. Update Category
```javascript
async function updateWebsiteCategory(id, data) {
    const response = await fetch(`/api/admin/website-categories/${id}`, {
        method: 'PUT',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    });
    
    return await response.json();
}
```

### 4. Toggle Activation
```javascript
async function toggleCategoryActivation(id, isActive) {
    const response = await fetch(`/api/admin/website-categories/${id}/toggle`, {
        method: 'PATCH',
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ is_active: isActive })
    });
    
    return await response.json();
}
```

---

## File Upload Guidelines

### Image Upload Specifications
- **Accepted formats**: JPEG, PNG, JPG, GIF
- **Maximum size**: 2MB (2048 KB)
- **Storage path**: `storage/app/public/website-categories/`
- **Naming convention**: `{slug}-{timestamp}.{extension}`
- **URL access**: `/storage/website-categories/{filename}`

### Frontend Image Handling
```javascript
// Validate image before upload
function validateImage(file) {
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!allowedTypes.includes(file.type)) {
        throw new Error('Invalid file type. Please upload JPEG, PNG, JPG, or GIF.');
    }
    
    if (file.size > maxSize) {
        throw new Error('File size too large. Maximum size is 2MB.');
    }
    
    return true;
}

### Form Data Handling
```javascript
// For multipart/form-data requests (with file uploads)
function createCategoryFormData(data) {
    const formData = new FormData();
    formData.append('nama_kategori', data.nama_kategori);
    
    if (data.slug) formData.append('slug', data.slug);
    if (data.image) formData.append('image', data.image);
    
    // Important: Send boolean as string for form-data
    if (data.is_active !== undefined) {
        formData.append('is_active', data.is_active ? 'true' : 'false');
    }
    
    return formData;
}

// For JSON requests (without file uploads)
function createCategoryJSON(data) {
    return {
        nama_kategori: data.nama_kategori,
        slug: data.slug || null,
        is_active: data.is_active !== undefined ? Boolean(data.is_active) : true
    };
}
```
```

---

## Performance Considerations

### Caching Strategy
- List endpoint can be cached for 5 minutes
- Individual category details can be cached for 15 minutes
- Statistics can be cached for 30 minutes
- Clear cache on any write operation

### Pagination Best Practices
- Default `per_page`: 15
- Maximum `per_page`: 100
- Use cursor-based pagination for large datasets
- Include total count for UI pagination controls

### Image Optimization
- Compress images before upload
- Consider image resizing service for thumbnails
- Use lazy loading for image lists
- Implement progressive image loading

---

## Security Notes

### Input Validation
- All input is validated and sanitized
- File uploads are restricted to images only
- Slug uniqueness is enforced
- XSS protection is implemented

### Authorization
- Admin role required for all operations
- Rate limiting implemented (100 requests per minute)
- CSRF protection enabled
- SQL injection protection via Eloquent ORM

---

## Testing Examples

### Unit Test Categories
```bash
# Test category creation
curl -X POST \
  -H "Authorization: Bearer {token}" \
  -F "nama_kategori=Test Category" \
  -F "is_active=true" \
  /api/admin/website-categories

# Test category listing
curl -H "Authorization: Bearer {token}" \
  /api/admin/website-categories

# Test category update
curl -X PUT \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"nama_kategori":"Updated Category","is_active":false}' \
  /api/admin/website-categories/1
```

This comprehensive API contract provides all the information needed for frontend implementation of website invitation category management with synchronized theme creation.
