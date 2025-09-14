# API Contract: User Theme Selection

## Overview
This document provides comprehensive API contracts for user-side theme browsing and selection in the Horuzt wedding invitation system. Users can browse themes by category and layout, preview demos, search with filters, and select themes for their wedding invitations.

## Business Process Flow
1. **Discovery**: User browses themes by category (Website/Video) and layout type (Scroll/Slide/Mobile)
2. **Preview**: User views theme previews and tests demo functionality
3. **Selection**: User selects preferred theme for their wedding invitation
4. **Activation**: Selected theme becomes active for user's invitation

## Authentication
- **Public Endpoints**: Category browsing, theme viewing, demo access (no auth required)
- **User Endpoints**: Theme selection, getting selected theme (requires `auth:sanctum`)
- **Headers**: 
  ```
  Authorization: Bearer {token} // for authenticated endpoints
  Content-Type: application/json
  Accept: application/json
  ```

---

## 🌐 Website Invitation Themes

### 1. Browse Website Theme Categories
**Endpoint**: `GET /themes/categories?type=website`
**Purpose**: Get all active website theme categories with their themes
**Authentication**: Public (no auth required)

#### Request Parameters
```json
{
  "type": "website",
  "include_themes": true,
  "limit": 20
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "type": "website",
    "categories": [
      {
        "id": 1,
        "name": "Modern Wedding",
        "slug": "modern-wedding",
        "type": "website",
        "description": "Contemporary and elegant website themes",
        "icon": "modern-icon.png",
        "sort_order": 1,
        "jenisThemas": [
          {
            "id": 1,
            "category_id": 1,
            "name": "Modern Blue",
            "price": 299000,
            "preview": "Clean and modern blue design",
            "preview_image": "/storage/themes/1/preview.jpg",
            "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/modern-blue",
            "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
            "sort_order": 1,
            "description": "A clean and modern blue theme perfect for contemporary couples"
          }
        ]
      }
    ],
    "total_categories": 8,
    "total_themes": 45
  },
  "message": "Website theme categories retrieved successfully"
}
```

### 2. Get Website Themes by Specific Category
**Endpoint**: `GET /themes/categories/{categoryId}`
**Purpose**: Get all themes within a specific website category
**Authentication**: Public

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "category": {
      "id": 1,
      "name": "Modern Wedding",
      "slug": "modern-wedding",
      "type": "website",
      "description": "Contemporary and elegant website themes"
    },
    "themes": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Modern Blue",
        "price": 299000,
        "preview": "Clean and modern blue design",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
        "description": "A clean and modern blue theme",
        "url_thema": "https://themes.horuzt.com/modern-blue",
        "sort_order": 1,
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website",
          "slug": "modern-wedding"
        }
      }
    ],
    "total_themes": 15
  }
}
```

### 3. Browse Website Themes by Layout Type
**Endpoint**: `GET /themes/layout?layout=Scroll&type=website`
**Purpose**: Filter website themes by layout type (Scroll/Slide/Mobile)
**Authentication**: Public

#### Request Parameters
```json
{
  "layout": "Scroll|Slide|Mobile",
  "type": "website",
  "limit": 20
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Scroll",
    "type": "website",
    "themes": [
      {
        "id": 5,
        "category_id": 1,
        "name": "Elegant Scroll",
        "price": 399000,
        "preview": "Beautiful scrolling website layout",
        "preview_image": "/storage/themes/5/preview.jpg",
        "thumbnail_image": "/storage/themes/5/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/elegant-scroll",
        "features": ["Scroll Animation", "Parallax Effect", "Mobile Responsive"],
        "description": "Elegant scrolling layout with smooth animations",
        "url_thema": "https://themes.horuzt.com/elegant-scroll",
        "sort_order": 1,
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website",
          "slug": "modern-wedding"
        }
      }
    ],
    "total": 8
  }
}
```

### 4. Search Website Themes
**Endpoint**: `GET /themes/search?query=modern&type=website`
**Purpose**: Advanced search with multiple filters for website themes
**Authentication**: Public

#### Request Parameters
```json
{
  "query": "modern",
  "type": "website",
  "category_id": 1,
  "price_min": 0,
  "price_max": 500000,
  "layout": "Scroll",
  "limit": 20
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "query": "modern",
    "filters": {
      "type": "website",
      "category_id": 1,
      "price_range": [0, 500000],
      "layout": "Scroll"
    },
    "themes": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Modern Blue",
        "price": 299000,
        "preview": "Clean and modern blue design",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "features": ["Responsive", "Modern Design", "Blue Theme"],
        "description": "A clean and modern blue theme",
        "url_thema": "https://themes.horuzt.com/modern-blue",
        "sort_order": 1,
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website",
          "slug": "modern-wedding"
        }
      }
    ],
    "total": 12
  }
}
```

---

## 📹 Video Invitation Themes

### 1. Browse Video Theme Categories
**Endpoint**: `GET /themes/categories?type=video`
**Purpose**: Get all active video theme categories with their themes
**Authentication**: Public

#### Request Parameters
```json
{
  "type": "video",
  "include_themes": true,
  "limit": 20
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "type": "video",
    "categories": [
      {
        "id": 10,
        "name": "Cinematic Wedding",
        "slug": "cinematic-wedding",
        "type": "video",
        "description": "Professional cinematic video invitation themes",
        "icon": "cinematic-icon.png",
        "sort_order": 1,
        "jenisThemas": [
          {
            "id": 25,
            "category_id": 10,
            "name": "Elegant Cinematic",
            "price": 599000,
            "preview": "Professional cinematic video template",
            "preview_image": "/storage/themes/25/preview.jpg",
            "thumbnail_image": "/storage/themes/25/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/elegant-cinematic",
            "features": ["4K Video", "Professional Editing", "Music Integration"],
            "sort_order": 1,
            "description": "High-quality cinematic video invitation"
          }
        ]
      }
    ],
    "total_categories": 5,
    "total_themes": 20
  }
}
```

### 2. Get Video Themes by Layout Type
**Endpoint**: `GET /themes/layout?layout=Slide&type=video`
**Purpose**: Filter video themes by presentation style
**Authentication**: Public

#### Request Parameters
```json
{
  "layout": "Slide|Mobile|Cinematic",
  "type": "video",
  "limit": 20
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Slide",
    "type": "video",
    "themes": [
      {
        "id": 28,
        "category_id": 10,
        "name": "Romantic Slideshow",
        "price": 449000,
        "preview": "Beautiful photo slideshow with romantic music",
        "preview_image": "/storage/themes/28/preview.jpg",
        "thumbnail_image": "/storage/themes/28/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/romantic-slideshow",
        "features": ["Photo Slideshow", "Romantic Music", "Fade Transitions"],
        "description": "Create beautiful photo slideshows with romantic backgrounds",
        "url_thema": "https://themes.horuzt.com/romantic-slideshow",
        "sort_order": 1,
        "category": {
          "id": 10,
          "name": "Cinematic Wedding",
          "type": "video",
          "slug": "cinematic-wedding"
        }
      }
    ],
    "total": 6
  }
}
```

### 3. Search Video Themes
**Endpoint**: `GET /themes/search?query=cinematic&type=video`
**Purpose**: Advanced search for video themes with filters
**Authentication**: Public

#### Request Parameters
```json
{
  "query": "cinematic",
  "type": "video",
  "category_id": 10,
  "price_min": 400000,
  "price_max": 800000,
  "layout": "Cinematic",
  "limit": 15
}
```

---

## 🎯 Theme Selection & Management

### 1. Get Theme Details
**Endpoint**: `GET /themes/theme/{themeId}`
**Purpose**: Get detailed information about a specific theme
**Authentication**: Public

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "id": 1,
    "category_id": 1,
    "name": "Modern Blue",
    "price": 299000,
    "preview": "Clean and modern blue design",
    "preview_image": "/storage/themes/1/preview.jpg",
    "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
    "demo_url": "https://demo.horuzt.com/modern-blue",
    "url_thema": "https://themes.horuzt.com/modern-blue",
    "features": ["Responsive", "Mobile-optimized", "SEO-friendly", "Music Support"],
    "description": "A clean and modern blue theme perfect for contemporary couples who want a sophisticated online invitation",
    "sort_order": 1,
    "category": {
      "id": 1,
      "name": "Modern Wedding",
      "type": "website",
      "slug": "modern-wedding",
      "description": "Contemporary and elegant website themes"
    },
    "specifications": {
      "type": "website",
      "layout": "Scroll",
      "mobile_optimized": true,
      "seo_friendly": true,
      "music_support": true,
      "photo_gallery": true,
      "rsvp_form": true,
      "social_sharing": true
    }
  }
}
```

### 2. Get Demo URL
**Endpoint**: `GET /themes/demo/{themeId}`
**Purpose**: Get demo URL for theme preview
**Authentication**: Public

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "theme_id": 1,
    "theme_name": "Modern Blue",
    "demo_url": "https://demo.horuzt.com/modern-blue"
  }
}
```

#### Error Response (404)
```json
{
  "status": false,
  "message": "Demo not available for this theme."
}
```

### 3. Select Theme (User Authentication Required)
**Endpoint**: `POST /themes/select`
**Purpose**: Select a theme for user's wedding invitation
**Authentication**: Required (`auth:sanctum`)

#### Request Body
```json
{
  "theme_id": 1
}
```

#### Response Success (200)
```json
{
  "status": true,
  "message": "Theme selected successfully",
  "data": {
    "theme": {
      "id": 1,
      "category_id": 1,
      "name": "Modern Blue",
      "price": 299000,
      "preview": "Clean and modern blue design",
      "preview_image": "/storage/themes/1/preview.jpg",
      "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
      "demo_url": "https://demo.horuzt.com/modern-blue",
      "url_thema": "https://themes.horuzt.com/modern-blue",
      "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
      "description": "A clean and modern blue theme",
      "category": {
        "id": 1,
        "name": "Modern Wedding",
        "type": "website",
        "slug": "modern-wedding"
      }
    },
    "selection": {
      "id": 123,
      "user_id": 456,
      "jenis_id": 1,
      "selected_at": "2025-01-01T12:00:00Z"
    }
  }
}
```

#### Error Response (400)
```json
{
  "status": false,
  "message": "Selected theme is not available."
}
```

### 4. Get User's Selected Theme
**Endpoint**: `GET /themes/selected`
**Purpose**: Get currently selected theme for authenticated user
**Authentication**: Required (`auth:sanctum`)

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "theme": {
      "id": 1,
      "category_id": 1,
      "name": "Modern Blue",
      "price": 299000,
      "preview": "Clean and modern blue design",
      "preview_image": "/storage/themes/1/preview.jpg",
      "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
      "demo_url": "https://demo.horuzt.com/modern-blue",
      "url_thema": "https://themes.horuzt.com/modern-blue",
      "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
      "description": "A clean and modern blue theme",
      "category": {
        "id": 1,
        "name": "Modern Wedding",
        "type": "website",
        "slug": "modern-wedding"
      }
    },
    "selected_at": "2025-01-01T12:00:00Z"
  }
}
```

#### No Theme Selected (200)
```json
{
  "status": true,
  "data": null,
  "message": "No theme selected."
}
```

---

## 🏆 Popular & Recommended Themes

### 1. Get Popular Themes
**Endpoint**: `GET /themes/popular?type=website`
**Purpose**: Get most selected themes by type
**Authentication**: Public

#### Request Parameters
```json
{
  "type": "website|video",
  "limit": 10
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "type": "website",
    "themes": [
      {
        "id": 1,
        "category_id": 1,
        "name": "Modern Blue",
        "price": 299000,
        "preview": "Clean and modern blue design",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "features": ["Responsive", "Mobile-optimized", "SEO-friendly"],
        "url_thema": "https://themes.horuzt.com/modern-blue",
        "sort_order": 1,
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website",
          "slug": "modern-wedding"
        },
        "result_themas_count": 150
      }
    ],
    "total": 10
  }
}
```

---

## 🔍 Frontend Implementation Guidelines

### Website Theme Browse Flow
```javascript
// 1. Load website categories
const categories = await fetch('/themes/categories?type=website');

// 2. Filter by layout if needed
const scrollThemes = await fetch('/themes/layout?layout=Scroll&type=website');

// 3. Search with filters
const searchResults = await fetch('/themes/search?query=modern&type=website&price_max=500000');

// 4. Get theme details
const themeDetails = await fetch('/themes/theme/1');

// 5. Preview demo
const demoUrl = await fetch('/themes/demo/1');
window.open(demoUrl.data.demo_url, '_blank');

// 6. Select theme (requires auth)
const selection = await fetch('/themes/select', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ theme_id: 1 })
});
```

### Video Theme Browse Flow
```javascript
// 1. Load video categories
const videoCategories = await fetch('/themes/categories?type=video');

// 2. Filter by video layout
const slideThemes = await fetch('/themes/layout?layout=Slide&type=video');

// 3. Search cinematic themes
const cinematicThemes = await fetch('/themes/search?query=cinematic&type=video');

// 4. Same selection process as website themes
```

### Theme Card Component Data Structure
```javascript
const ThemeCard = {
  id: 1,
  name: "Modern Blue",
  price: 299000,
  thumbnail_image: "/storage/themes/1/thumbnail.jpg",
  preview_image: "/storage/themes/1/preview.jpg", // for modal/detail view
  demo_url: "https://demo.horuzt.com/modern-blue",
  features: ["Responsive", "Mobile-optimized"],
  category: {
    name: "Modern Wedding",
    type: "website"
  },
  is_popular: false, // based on result_themas_count
  is_selected: false // if user has selected this theme
};
```

---

## 📱 Mobile Optimization

### Layout Types Explanation
- **Scroll**: Single-page scrolling layout, perfect for mobile viewing
- **Slide**: Multi-slide presentation style, touch-friendly navigation  
- **Mobile**: Specifically optimized for mobile-first experience
- **Cinematic**: Video-focused with full-screen presentation (video themes)

### Mobile-Specific Features
- Touch-friendly navigation
- Optimized image loading
- Responsive breakpoints
- Fast preview loading
- Offline demo caching (optional)

---

## ⚡ Performance Considerations

### Image Loading Strategy
- **Thumbnails**: Load immediately for grid view (300x200px, optimized)
- **Previews**: Load on hover/tap (800x600px, high quality)
- **Lazy Loading**: Implement for better performance
- **CDN**: All images served via optimized CDN

### Caching Strategy
- **Categories**: Cache for 30 minutes
- **Popular Themes**: Cache for 1 hour
- **Search Results**: Cache for 15 minutes
- **Theme Details**: Cache for 2 hours

### API Response Times
- **Category List**: < 200ms
- **Theme Search**: < 300ms
- **Theme Selection**: < 500ms
- **Demo URL**: < 100ms

---

## 🛡️ Error Handling

### Common Error Scenarios
```javascript
// Handle theme unavailable
if (response.status === 400 && response.data.message.includes('not available')) {
  showMessage('This theme is currently unavailable. Please try another one.');
}

// Handle authentication required
if (response.status === 401) {
  redirectToLogin();
}

// Handle network errors
if (!response.ok) {
  showMessage('Connection error. Please check your internet and try again.');
}
```

### User Experience Guidelines
- Show loading states for all API calls
- Provide fallback for failed image loads
- Cache user's browsing history
- Remember last viewed category/layout
- Implement offline browsing for popular themes

---

This completes the user-side API contract for theme selection. The frontend team can use this documentation to implement a comprehensive theme browsing and selection experience for both website and video invitation types.
