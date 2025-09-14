# Theme Management API Documentation

## Overview
This API provides comprehensive theme management functionality for the wedding invitation system, including admin CRUD operations and user theme selection.

## Authentication
Most endpoints require authentication via Laravel Sanctum:
```
Authorization: Bearer {your-token}
```

## Admin Theme Management

### Category Management

#### Get All Categories
```http
GET /api/admin/categories
```

**Query Parameters:**
- `type` (optional): Filter by category type (`website` or `video`)
- `status` (optional): Filter by status (`active` or `inactive`)
- `search` (optional): Search by category name
- `per_page` (optional): Items per page (default: 15)

**Response:**
```json
{
  "status": true,
  "data": {
    "data": [
      {
        "id": 1,
        "name": "Modern Website Themes",
        "type": "website",
        "description": "Modern and elegant website themes",
        "is_active": true,
        "sort_order": 1,
        "themes_count": 5,
        "created_at": "2024-01-01T00:00:00.000000Z"
      }
    ],
    "pagination": {...}
  },
  "summary": {
    "total_categories": 10,
    "active_categories": 8,
    "website_categories": 6,
    "video_categories": 4
  }
}
```

#### Create Category
```http
POST /api/admin/categories
```

**Request Body:**
```json
{
  "name": "Premium Themes",
  "type": "website",
  "description": "Premium wedding theme collection",
  "is_active": true,
  "sort_order": 10
}
```

#### Update Category
```http
PUT /api/admin/categories/{id}
```

#### Toggle Category Activation
```http
PATCH /api/admin/categories/{id}/toggle-activation
```

**Request Body:**
```json
{
  "is_active": true
}
```

#### Update Sort Order
```http
PATCH /api/admin/categories/sort-order
```

**Request Body:**
```json
{
  "categories": [
    {"id": 1, "sort_order": 1},
    {"id": 2, "sort_order": 2}
  ]
}
```

#### Get Category Statistics
```http
GET /api/admin/categories/statistics/overview
```

#### Delete Category
```http
DELETE /api/admin/categories/{id}
```

### Theme Management

#### Get All Themes
```http
GET /api/admin/themes
```

**Query Parameters:**
- `category_id` (optional): Filter by category
- `type` (optional): Filter by category type (`website` or `video`)
- `status` (optional): Filter by status (`active` or `inactive`)
- `search` (optional): Search by theme name
- `per_page` (optional): Items per page (default: 15)

**Response:**
```json
{
  "status": true,
  "data": {
    "data": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Elegant Wedding",
        "price": 99.99,
        "preview": "Beautiful elegant theme preview",
        "url_thema": "https://example.com/theme",
        "demo_url": "https://example.com/demo",
        "is_active": true,
        "description": "Perfect for elegant weddings",
        "features": ["Responsive", "Dark Mode", "Gallery"],
        "sort_order": 1,
        "category": {
          "id": 1,
          "name": "Modern Website Themes",
          "type": "website"
        }
      }
    ]
  },
  "summary": {
    "total_themes": 25,
    "active_themes": 20,
    "website_themes": 15,
    "video_themes": 10
  }
}
```

#### Create Theme
```http
POST /api/admin/themes
```

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Romantic Theme",
  "price": 79.99,
  "preview": "Romantic wedding theme",
  "url_thema": "https://example.com/romantic",
  "demo_url": "https://example.com/demo/romantic",
  "description": "Perfect for romantic weddings",
  "features": ["Photo Gallery", "Music Player", "RSVP Form"],
  "is_active": true,
  "sort_order": 5
}
```

#### Update Theme
```http
PUT /api/admin/themes/{id}
```

#### Toggle Theme Activation
```http
PATCH /api/admin/themes/{id}/toggle-activation
```

#### Update Theme Sort Order
```http
PATCH /api/admin/themes/sort-order
```

#### Get Available Categories
```http
GET /api/admin/themes/categories/available
```

**Query Parameters:**
- `type` (optional): Filter by type (`website` or `video`)
- `include_inactive` (optional): Include inactive categories

#### Delete Theme
```http
DELETE /api/admin/themes/{id}
```

## Public Theme Browsing (No Authentication Required)

### Get Categories with Themes
```http
GET /api/themes/categories
```

**Query Parameters:**
- `type` (optional): Filter by type (`website` or `video`, default: `website`)

**Response:**
```json
{
  "status": true,
  "data": {
    "type": "website",
    "categories": [
      {
        "id": 1,
        "name": "Modern Themes",
        "type": "website",
        "description": "Modern wedding themes",
        "jenis_themas": [
          {
            "id": 1,
            "name": "Elegant Wedding",
            "price": 99.99,
            "preview": "Beautiful elegant theme",
            "demo_url": "https://example.com/demo",
            "features": ["Responsive", "Gallery"]
          }
        ]
      }
    ],
    "total_categories": 5,
    "total_themes": 25
  }
}
```

### Get Themes by Category
```http
GET /api/themes/categories/{categoryId}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Modern Themes",
      "type": "website"
    },
    "themes": [
      {
        "id": 1,
        "name": "Elegant Wedding",
        "price": 99.99,
        "preview": "Beautiful theme",
        "demo_url": "https://example.com/demo",
        "features": ["Responsive", "Gallery"],
        "description": "Perfect for elegant weddings"
      }
    ],
    "total_themes": 8
  }
}
```

### Get Theme Details
```http
GET /api/themes/theme/{themeId}
```

**Response:**
```json
{
  "status": true,
  "data": {
    "id": 1,
    "category_id": 1,
    "name": "Elegant Wedding",
    "price": 99.99,
    "preview": "Beautiful elegant theme",
    "url_thema": "https://example.com/theme",
    "demo_url": "https://example.com/demo",
    "description": "Perfect for elegant weddings",
    "features": ["Responsive", "Dark Mode", "Gallery"],
    "category": {
      "id": 1,
      "name": "Modern Themes",
      "type": "website"
    }
  }
}
```

### Get Popular Themes
```http
GET /api/themes/popular
```

**Query Parameters:**
- `type` (optional): Filter by type (`website` or `video`, default: `website`)
- `limit` (optional): Number of themes to return (default: 10)

## User Theme Selection (Authentication Required)

### Select a Theme
```http
POST /api/themes/select
```

**Request Body:**
```json
{
  "theme_id": 1
}
```

**Response:**
```json
{
  "status": true,
  "message": "Theme selected successfully",
  "data": {
    "theme": {
      "id": 1,
      "name": "Elegant Wedding",
      "price": 99.99,
      "category": {
        "id": 1,
        "name": "Modern Themes",
        "type": "website"
      }
    },
    "selection": {
      "id": 1,
      "user_id": 1,
      "jenis_id": 1,
      "selected_at": "2024-01-01T12:00:00.000000Z"
    }
  }
}
```

### Get Selected Theme
```http
GET /api/themes/selected
```

**Response:**
```json
{
  "status": true,
  "data": {
    "theme": {
      "id": 1,
      "name": "Elegant Wedding",
      "price": 99.99,
      "preview": "Beautiful theme",
      "url_thema": "https://example.com/theme",
      "demo_url": "https://example.com/demo",
      "features": ["Responsive", "Gallery"],
      "category": {
        "id": 1,
        "name": "Modern Themes",
        "type": "website"
      }
    },
    "selected_at": "2024-01-01T12:00:00.000000Z"
  }
}
```

## Wedding Profile Integration

The selected theme is automatically included in the wedding profile endpoint:

```http
GET /api/v1/user/wedding-profile
```

**Response includes:**
```json
{
  "data": {
    "themes": {
      "selected_theme": {
        "id": 1,
        "name": "Elegant Wedding",
        "price": 99.99,
        "preview": "Beautiful theme",
        "url_thema": "https://example.com/theme",
        "demo_url": "https://example.com/demo",
        "features": ["Responsive", "Gallery"],
        "description": "Perfect for elegant weddings",
        "category": {
          "id": 1,
          "name": "Modern Themes",
          "type": "website"
        },
        "selected_at": "2024-01-01T12:00:00.000000Z"
      },
      "legacy_themes": []
    }
  }
}
```

## Error Responses

All endpoints return consistent error responses:

```json
{
  "status": false,
  "message": "Error description",
  "errors": {
    "field": ["Validation error message"]
  }
}
```

## Status Codes

- `200`: Success
- `201`: Created successfully
- `400`: Bad request / validation error
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not found
- `422`: Validation failed
- `500`: Server error

## Frontend Implementation Notes

1. **Theme Selection Flow:**
   - Browse categories → View themes → Select theme
   - Selected theme appears in wedding profile

2. **Admin Management:**
   - Categories must be active for themes to be visible to users
   - Themes must be active to be selectable
   - Sort order controls display order

3. **Theme Types:**
   - `website`: Traditional website themes
   - `video`: Video-based invitation themes

4. **Real-time Updates:**
   - Changes to category/theme activation instantly affect user visibility
   - Theme selection immediately updates wedding profile

5. **Caching Considerations:**
   - Public theme browsing can be cached
   - User selections should not be cached
   - Admin changes should invalidate public caches