# API Contract: Admin Theme Management

## Overview
This document provides comprehensive API contracts for admin-side theme and category management in the Horuzt wedding invitation system. Admins can manage theme categories (Website/Video types) and individual themes with image uploads, bulk operations, and activation controls.

## Business Process Flow
1. **Category Management**: Admin creates and manages theme categories (Website/Video types)
2. **Theme Management**: Admin uploads themes with preview images and sets pricing
3. **Bulk Operations**: Admin can activate/deactivate multiple items efficiently
4. **Content Organization**: Auto-generated slugs and sort ordering for better organization

## Authentication
All admin endpoints require:
- **Middleware**: `auth:sanctum`, `role:admin`
- **Headers**: 
  ```
  Authorization: Bearer {token}
  Content-Type: application/json
  Accept: application/json
  ```

---

## 📁 Category Management APIs

### 1. Get All Categories
**Endpoint**: `GET /admin/categories`
**Purpose**: Retrieve all theme categories with pagination and filtering

#### Request Parameters
```json
{
  "page": 1,
  "per_page": 10,
  "type": "website|video",
  "is_active": true|false,
  "search": "category name"
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "categories": [
      {
        "id": 1,
        "name": "Modern Wedding",
        "slug": "modern-wedding",
        "type": "website",
        "description": "Contemporary and elegant wedding themes",
        "icon": "modern-icon.png",
        "is_active": true,
        "sort_order": 1,
        "themes_count": 15,
        "created_at": "2025-01-01T00:00:00Z",
        "updated_at": "2025-01-01T00:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 25,
      "last_page": 3
    },
    "statistics": {
      "total_categories": 25,
      "active_categories": 20,
      "website_categories": 15,
      "video_categories": 10
    }
  },
  "message": "Categories retrieved successfully"
}
```

### 2. Create Category
**Endpoint**: `POST /admin/categories`
**Purpose**: Create new theme category with auto-generated slug

#### Request Body
```json
{
  "name": "Classic Elegance",
  "type": "website",
  "description": "Timeless and sophisticated wedding themes",
  "icon": "classic-icon.png",
  "is_active": true,
  "sort_order": 5
}
```

#### Response Success (201)
```json
{
  "status": true,
  "data": {
    "id": 26,
    "name": "Classic Elegance",
    "slug": "classic-elegance",
    "type": "website",
    "description": "Timeless and sophisticated wedding themes",
    "icon": "classic-icon.png",
    "is_active": true,
    "sort_order": 5,
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z"
  },
  "message": "Category created successfully"
}
```

#### Validation Errors (422)
```json
{
  "status": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required."],
    "type": ["The type must be either website or video."]
  }
}
```

### 3. Update Category
**Endpoint**: `PUT /admin/categories/{id}`
**Purpose**: Update existing category details

#### Request Body
```json
{
  "name": "Updated Classic Elegance",
  "description": "Updated description",
  "icon": "new-icon.png",
  "is_active": false,
  "sort_order": 10
}
```

### 4. Delete Category
**Endpoint**: `DELETE /admin/categories/{id}`
**Purpose**: Delete category and handle theme dependencies

#### Response Success (200)
```json
{
  "status": true,
  "message": "Category deleted successfully",
  "data": {
    "deleted_category_id": 26,
    "affected_themes": 5,
    "cascade_action": "themes_deactivated"
  }
}
```

### 5. Bulk Toggle Activation
**Endpoint**: `PATCH /admin/categories/bulk-toggle`
**Purpose**: Bulk activate/deactivate categories

#### Request Body
```json
{
  "category_ids": [1, 2, 3, 4],
  "is_active": true,
  "cascade_to_themes": true
}
```

#### Response Success (200)
```json
{
  "status": true,
  "message": "Bulk activation completed successfully",
  "data": {
    "updated_categories": 4,
    "affected_themes": 25,
    "summary": {
      "categories_activated": 4,
      "themes_activated": 25
    }
  }
}
```

### 6. Get Category Statistics
**Endpoint**: `GET /admin/categories/statistics/overview`
**Purpose**: Get comprehensive category analytics

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "overview": {
      "total_categories": 25,
      "active_categories": 20,
      "inactive_categories": 5
    },
    "by_type": {
      "website": {
        "total": 15,
        "active": 12,
        "themes_count": 45
      },
      "video": {
        "total": 10,
        "active": 8,
        "themes_count": 30
      }
    },
    "performance": {
      "most_popular_category": "Modern Wedding",
      "least_used_category": "Vintage Style",
      "average_themes_per_category": 3.2
    }
  }
}
```

---

## 🎨 Theme Management APIs

### 1. Get All Themes
**Endpoint**: `GET /admin/themes`
**Purpose**: Retrieve all themes with detailed information

#### Request Parameters
```json
{
  "page": 1,
  "per_page": 15,
  "category_id": 1,
  "is_active": true|false,
  "search": "theme name",
  "price_min": 0,
  "price_max": 1000000,
  "sort_by": "name|price|created_at|popularity",
  "sort_order": "asc|desc"
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "themes": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Modern Blue",
        "price": 299000,
        "preview": "Preview description",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "url_thema": "https://themes.horuzt.com/modern-blue",
        "is_active": true,
        "description": "A clean and modern blue theme",
        "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
        "sort_order": 1,
        "created_at": "2025-01-01T00:00:00Z",
        "updated_at": "2025-01-01T00:00:00Z",
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website",
          "slug": "modern-wedding"
        },
        "usage_statistics": {
          "total_selections": 150,
          "this_month": 25
        }
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 75,
      "last_page": 5
    }
  }
}
```

### 2. Create Theme
**Endpoint**: `POST /admin/themes`
**Purpose**: Create new theme with image upload support

#### Request Body (multipart/form-data)
```json
{
  "category_id": 1,
  "name": "Elegant Rose",
  "price": 399000,
  "preview": "A beautiful rose-themed wedding invitation",
  "demo_url": "https://demo.horuzt.com/elegant-rose",
  "url_thema": "https://themes.horuzt.com/elegant-rose",
  "description": "Romantic and elegant with rose motifs",
  "features": ["Responsive", "Animation", "Music Support"],
  "is_active": true,
  "sort_order": 1,
  "preview_image": "[FILE]",
  "thumbnail_image": "[FILE]"
}
```

#### Response Success (201)
```json
{
  "status": true,
  "data": {
    "id": 76,
    "category_id": 1,
    "name": "Elegant Rose",
    "price": 399000,
    "preview": "A beautiful rose-themed wedding invitation",
    "preview_image": "/storage/themes/76/preview.jpg",
    "thumbnail_image": "/storage/themes/76/thumbnail.jpg",
    "demo_url": "https://demo.horuzt.com/elegant-rose",
    "url_thema": "https://themes.horuzt.com/elegant-rose",
    "is_active": true,
    "description": "Romantic and elegant with rose motifs",
    "features": ["Responsive", "Animation", "Music Support"],
    "sort_order": 1,
    "created_at": "2025-01-01T00:00:00Z",
    "updated_at": "2025-01-01T00:00:00Z",
    "image_processing": {
      "preview_dimensions": "800x600",
      "thumbnail_dimensions": "300x200",
      "format": "JPEG",
      "quality": 85
    }
  },
  "message": "Theme created successfully with image processing"
}
```

#### Image Upload Requirements
- **Preview Image**: Max 5MB, 800x600px (auto-resized), JPEG/PNG/WebP
- **Thumbnail Image**: Max 2MB, 300x200px (auto-resized), JPEG/PNG/WebP
- **Storage**: `/storage/themes/{theme_id}/`
- **Naming**: `preview.{ext}`, `thumbnail.{ext}`

### 3. Update Theme
**Endpoint**: `PUT /admin/themes/{id}`
**Purpose**: Update theme with optional image replacement

#### Request Body (multipart/form-data)
```json
{
  "name": "Updated Elegant Rose",
  "price": 450000,
  "preview": "Updated description",
  "demo_url": "https://demo.horuzt.com/elegant-rose-v2",
  "description": "Updated romantic theme with new features",
  "features": ["Responsive", "Animation", "Music Support", "Video Background"],
  "is_active": true,
  "sort_order": 2,
  "preview_image": "[FILE]",
  "thumbnail_image": "[FILE]"
}
```

### 4. Delete Theme
**Endpoint**: `DELETE /admin/themes/{id}`
**Purpose**: Delete theme and clean up associated files

#### Response Success (200)
```json
{
  "status": true,
  "message": "Theme deleted successfully",
  "data": {
    "deleted_theme_id": 76,
    "deleted_files": [
      "/storage/themes/76/preview.jpg",
      "/storage/themes/76/thumbnail.jpg"
    ],
    "user_selections_affected": 5,
    "cleanup_status": "completed"
  }
}
```

### 5. Bulk Toggle Theme Activation
**Endpoint**: `PATCH /admin/themes/bulk-toggle`
**Purpose**: Bulk activate/deactivate themes

#### Request Body
```json
{
  "theme_ids": [1, 2, 3, 4, 5],
  "is_active": false,
  "reason": "Maintenance update"
}
```

#### Response Success (200)
```json
{
  "status": true,
  "message": "Bulk theme activation completed",
  "data": {
    "updated_themes": 5,
    "affected_user_selections": 25,
    "summary": {
      "themes_deactivated": 5,
      "notifications_sent": 25
    }
  }
}
```

### 6. Get Available Categories for Theme Creation
**Endpoint**: `GET /admin/themes/categories/available`
**Purpose**: Get active categories for theme assignment

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "website_categories": [
      {
        "id": 1,
        "name": "Modern Wedding",
        "slug": "modern-wedding",
        "themes_count": 15
      }
    ],
    "video_categories": [
      {
        "id": 2,
        "name": "Cinematic Wedding",
        "slug": "cinematic-wedding",
        "themes_count": 8
      }
    ],
    "total_categories": 25
  }
}
```

---

## 🔧 Sort Order Management

### Update Category Sort Order
**Endpoint**: `PATCH /admin/categories/sort-order`

#### Request Body
```json
{
  "updates": [
    {"id": 1, "sort_order": 1},
    {"id": 2, "sort_order": 2},
    {"id": 3, "sort_order": 3}
  ]
}
```

### Update Theme Sort Order
**Endpoint**: `PATCH /admin/themes/sort-order`

#### Request Body
```json
{
  "updates": [
    {"id": 1, "sort_order": 1},
    {"id": 2, "sort_order": 2}
  ],
  "category_id": 1
}
```

---

## 🔄 Business Rules & Validations

### Category Rules
- **Name**: Required, unique (case-insensitive), max 255 characters
- **Type**: Required, must be 'website' or 'video'
- **Slug**: Auto-generated from name, unique with counter if needed
- **Sort Order**: Integer, default based on creation order

### Theme Rules
- **Category**: Must be active category
- **Name**: Required, max 255 characters
- **Price**: Numeric, minimum 0
- **Images**: Optional but recommended, auto-processed
- **Features**: JSON array of strings
- **URLs**: Valid URL format for demo_url and url_thema

### Cascade Rules
- **Category Deactivation**: All themes in category become inactive
- **Category Deletion**: All themes moved to 'Uncategorized' or deactivated
- **Theme Deletion**: User selections remain but marked as 'theme_unavailable'

---

## ⚠️ Error Handling

### Common Error Responses
```json
{
  "status": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation message"]
  },
  "error_code": "VALIDATION_ERROR",
  "debug_info": "Additional debug information"
}
```

### Error Codes
- `VALIDATION_ERROR` (422): Request validation failed
- `NOT_FOUND` (404): Resource not found
- `UNAUTHORIZED` (401): Authentication required
- `FORBIDDEN` (403): Insufficient permissions
- `SERVER_ERROR` (500): Internal server error
- `FILE_UPLOAD_ERROR` (422): Image upload/processing failed

---

## 📊 Usage Analytics

### Category Performance Metrics
- **Theme Count**: Number of themes per category
- **User Selections**: How many users selected themes from this category
- **Revenue**: Total revenue from category themes
- **Growth**: Month-over-month growth

### Theme Performance Metrics
- **Selection Count**: Number of times theme was selected
- **Revenue**: Total revenue from theme
- **Conversion Rate**: View-to-selection ratio
- **User Rating**: Average user satisfaction (if implemented)

---

## 🔐 Security Considerations

### Authentication
- All endpoints require valid admin token
- Token expiration handled automatically
- Role-based access control enforced

### File Upload Security
- File type validation (JPEG, PNG, WebP only)
- File size limits enforced
- Malicious file detection
- Secure storage with proper permissions

### Data Validation
- SQL injection prevention
- XSS protection
- CSRF protection
- Input sanitization

---

This completes the admin-side API contract for theme management. The frontend team can use this documentation to implement the admin panel with full CRUD operations, bulk management, image uploads, and comprehensive analytics.
