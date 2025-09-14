# API Contract Summary: Theme Management System

## Overview
This document provides a comprehensive overview of all API contracts for the Horuzt wedding invitation theme management system. It serves as a quick reference guide for frontend developers to understand the complete system architecture and integration points.

## 📁 Documentation Structure

### 1. [Admin Theme Management API](./ADMIN_THEME_MANAGEMENT_API.md)
**Purpose**: Complete admin-side management of categories and themes
**Authentication**: Admin role required
**Key Features**:
- Category CRUD operations with auto-slug generation
- Theme CRUD with image upload and processing
- Bulk activation/deactivation operations
- Statistics and analytics dashboard
- Sort order management
- File cleanup and validation

### 2. [User Theme Selection API](./USER_THEME_SELECTION_API.md)
**Purpose**: User-side theme browsing and selection
**Authentication**: Mixed (public browsing, auth for selection)
**Key Features**:
- Public theme browsing by category and layout
- Advanced search with multiple filters
- Demo preview functionality
- Theme selection and management
- Popular themes and recommendations
- Mobile-optimized responses

### 3. [Website Invitation Themes API](./WEBSITE_INVITATION_THEMES_API.md)
**Purpose**: Specialized API for website invitation themes
**Authentication**: Mixed (public browsing, auth for selection)
**Key Features**:
- Website-specific layout types (Scroll, Slide, Mobile)
- Interactive demo previews
- SEO and performance specifications
- Responsive design features
- Social sharing capabilities
- Real-time customization options

### 4. [Video Invitation Themes API](./VIDEO_INVITATION_THEMES_API.md)
**Purpose**: Specialized API for video invitation themes
**Authentication**: Mixed (public browsing, auth for selection/production)
**Key Features**:
- Video layout types (Cinematic, Slideshow, Mobile, Social)
- Professional video production workflow
- Asset upload and management
- Production status tracking
- Multiple resolution and format support
- Social media optimization

---

## 🏗️ System Architecture Overview

### Base URL Structure
```
Production: https://api.horuzt.com
Staging: https://staging-api.horuzt.com
Development: http://localhost:8000
```

### API Versioning
- **Current Version**: v1
- **URL Pattern**: `/api/v1/{endpoint}`
- **Header**: `Accept: application/vnd.api+json;version=1`

---

## 🔐 Authentication & Authorization

### Authentication Methods
```javascript
// Sanctum Token Authentication
headers: {
  'Authorization': 'Bearer {sanctum_token}',
  'Content-Type': 'application/json',
  'Accept': 'application/json'
}
```

### User Roles & Permissions
- **Admin**: Full system access, theme management, user management
- **User**: Theme browsing, selection, personal content management
- **Guest**: Public theme browsing, demo access (no auth required)

### Protected Endpoints
```javascript
// Admin only
'/admin/categories/*'
'/admin/themes/*'

// User authentication required
'/themes/select'
'/themes/selected'
'/video-production/*'

// Public access
'/themes/categories'
'/themes/search'
'/themes/demo/*'
```

---

## 📊 Complete API Endpoint Reference

### 🛠️ Admin Endpoints
```
Category Management:
GET    /admin/categories              - List categories
POST   /admin/categories              - Create category
GET    /admin/categories/{id}         - Get category details
PUT    /admin/categories/{id}         - Update category
DELETE /admin/categories/{id}         - Delete category
PATCH  /admin/categories/bulk-toggle  - Bulk activation
PATCH  /admin/categories/sort-order   - Update sort order
GET    /admin/categories/statistics   - Category analytics

Theme Management:
GET    /admin/themes                  - List themes
POST   /admin/themes                  - Create theme (with images)
GET    /admin/themes/{id}             - Get theme details
PUT    /admin/themes/{id}             - Update theme
DELETE /admin/themes/{id}             - Delete theme
PATCH  /admin/themes/bulk-toggle      - Bulk activation
PATCH  /admin/themes/sort-order       - Update sort order
GET    /admin/themes/categories       - Available categories
```

### 👥 User Endpoints
```
Theme Browsing (Public):
GET    /themes/categories             - Browse categories
GET    /themes/categories/{id}        - Category themes
GET    /themes/theme/{id}             - Theme details
GET    /themes/layout                 - Layout-based filtering
GET    /themes/search                 - Advanced search
GET    /themes/popular                - Popular themes
GET    /themes/demo/{id}              - Demo access

Theme Selection (Auth Required):
POST   /themes/select                 - Select theme
GET    /themes/selected               - Get selected theme

Video Production (Auth Required):
POST   /video-production/assets       - Upload production assets
GET    /video-production/status/{id}  - Production status
```

---

## 🔄 Data Flow Architecture

### Theme Management Flow
```
Admin Creates Category → Auto-generates Slug → Themes Added → Image Processing → User Browsing → Selection → Activation
```

### Video Production Flow
```
User Selects Video Theme → Uploads Assets → Production Queue → Editing → Review → Delivery → Final Approval
```

### Search & Discovery Flow
```
User Filters (Type/Layout/Price) → Search API → Faceted Results → Demo Preview → Selection Decision
```

---

## 📱 Response Format Standards

### Success Response Structure
```json
{
  "status": true,
  "data": {
    // Response data
  },
  "message": "Operation successful",
  "meta": {
    "timestamp": "2025-01-01T12:00:00Z",
    "version": "v1"
  }
}
```

### Error Response Structure
```json
{
  "status": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation message"]
  },
  "error_code": "ERROR_TYPE",
  "meta": {
    "timestamp": "2025-01-01T12:00:00Z",
    "request_id": "uuid-123"
  }
}
```

### Pagination Structure
```json
{
  "data": [...],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 150,
    "last_page": 10,
    "has_more": true
  }
}
```

---

## 🎯 Integration Guidelines

### Frontend State Management
```javascript
// Recommended Redux/Zustand store structure
const themeStore = {
  // Categories
  categories: {
    website: [],
    video: []
  },
  
  // Browsing state
  browsing: {
    currentType: 'website',
    currentLayout: 'Scroll',
    filters: {},
    results: []
  },
  
  // User selection
  selection: {
    selectedTheme: null,
    selectionDate: null,
    customizations: {}
  },
  
  // Video production (if applicable)
  production: {
    status: 'pending',
    assets: [],
    timeline: {}
  }
};
```

### Image Handling Best Practices
```javascript
// Lazy loading implementation
const ThemeImage = ({ theme }) => {
  const [imageUrl, setImageUrl] = useState(theme.thumbnail_image);
  const [isHovered, setIsHovered] = useState(false);
  
  useEffect(() => {
    if (isHovered && theme.preview_image) {
      // Preload full preview on hover
      const img = new Image();
      img.src = theme.preview_image;
      img.onload = () => setImageUrl(theme.preview_image);
    }
  }, [isHovered]);
  
  return (
    <img 
      src={imageUrl}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      loading="lazy"
      alt={theme.name}
    />
  );
};
```

### Demo Integration
```javascript
// Universal demo handler
const openDemo = async (themeId, type = 'website') => {
  try {
    const response = await fetch(`/themes/demo/${themeId}`);
    const demo = await response.json();
    
    if (type === 'video') {
      // Open video player modal
      openVideoModal(demo.data.video_demos.preview_quality.url);
    } else {
      // Open website demo in new window
      window.open(demo.data.demo_url, 'demo', 'width=1200,height=800');
    }
  } catch (error) {
    console.error('Demo failed to load:', error);
  }
};
```

---

## ⚡ Performance Optimization

### Caching Strategy
```javascript
// API response caching
const apiCache = {
  categories: { ttl: 30 * 60 * 1000 }, // 30 minutes
  themes: { ttl: 15 * 60 * 1000 },     // 15 minutes
  search: { ttl: 5 * 60 * 1000 },      // 5 minutes
  popular: { ttl: 60 * 60 * 1000 }     // 1 hour
};

// Image optimization
const imageOptimization = {
  thumbnail: '300x200_q80',
  preview: '800x600_q85',
  demo: '1200x800_q90'
};
```

### Loading States
```javascript
// Unified loading states
const LoadingStates = {
  IDLE: 'idle',
  LOADING: 'loading',
  SUCCESS: 'success',
  ERROR: 'error'
};

// Usage in components
const [loadingState, setLoadingState] = useState(LoadingStates.IDLE);
```

---

## 🔍 Search & Filter Implementation

### Search Filter Object
```javascript
const searchFilters = {
  // Universal filters
  type: 'website|video',
  query: 'search term',
  category_id: 1,
  price_min: 0,
  price_max: 1000000,
  
  // Layout filters
  layout: 'Scroll|Slide|Mobile|Cinematic',
  
  // Feature filters
  features: ['RSVP Form', 'Photo Gallery'],
  
  // Video-specific filters
  duration: '30-60|60-90',
  resolution: 'HD|Full HD|4K',
  aspect_ratio: '16:9|1:1|9:16',
  
  // Pagination
  page: 1,
  limit: 20
};
```

### Filter State Management
```javascript
const useThemeFilters = () => {
  const [filters, setFilters] = useState(defaultFilters);
  const [results, setResults] = useState([]);
  const [loading, setLoading] = useState(false);
  
  const applyFilters = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams(filters);
      const response = await fetch(`/themes/search?${params}`);
      const data = await response.json();
      setResults(data.data.themes);
    } catch (error) {
      console.error('Search failed:', error);
    } finally {
      setLoading(false);
    }
  }, [filters]);
  
  return { filters, setFilters, results, loading, applyFilters };
};
```

---

## 🚨 Error Handling Patterns

### Global Error Handler
```javascript
const apiErrorHandler = (error, context) => {
  const errorMap = {
    401: 'Please log in to continue',
    403: 'You don\'t have permission for this action',
    404: 'Theme not found or unavailable',
    422: 'Please check your input and try again',
    500: 'Server error. Please try again later'
  };
  
  const message = errorMap[error.status] || 'Something went wrong';
  
  // Log for debugging
  console.error(`API Error [${context}]:`, error);
  
  // Show user-friendly message
  showNotification(message, 'error');
  
  // Track for analytics
  trackError(error, context);
};
```

### Retry Logic
```javascript
const apiWithRetry = async (url, options = {}, maxRetries = 3) => {
  let lastError;
  
  for (let i = 0; i <= maxRetries; i++) {
    try {
      const response = await fetch(url, options);
      if (response.ok) return response;
      
      // Don't retry on client errors
      if (response.status >= 400 && response.status < 500) {
        throw new Error(`Client error: ${response.status}`);
      }
      
      lastError = new Error(`Server error: ${response.status}`);
    } catch (error) {
      lastError = error;
      
      // Wait before retry (exponential backoff)
      if (i < maxRetries) {
        await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 1000));
      }
    }
  }
  
  throw lastError;
};
```

---

## 📈 Analytics & Tracking

### Event Tracking
```javascript
// Theme interaction events
const trackThemeEvent = (event, themeData) => {
  analytics.track(event, {
    theme_id: themeData.id,
    theme_name: themeData.name,
    theme_type: themeData.category.type,
    theme_price: themeData.price,
    user_id: currentUser?.id,
    timestamp: Date.now()
  });
};

// Usage examples
trackThemeEvent('theme_viewed', theme);
trackThemeEvent('demo_opened', theme);
trackThemeEvent('theme_selected', theme);
```

### Performance Monitoring
```javascript
// API performance tracking
const trackApiPerformance = (endpoint, duration, success) => {
  analytics.track('api_performance', {
    endpoint,
    duration,
    success,
    timestamp: Date.now()
  });
};

// Usage in API calls
const startTime = performance.now();
try {
  const response = await fetch(endpoint);
  const duration = performance.now() - startTime;
  trackApiPerformance(endpoint, duration, true);
  return response;
} catch (error) {
  const duration = performance.now() - startTime;
  trackApiPerformance(endpoint, duration, false);
  throw error;
}
```

---

## 🔧 Development Tools

### API Testing with Postman
```javascript
// Environment variables for testing
{
  "base_url": "http://localhost:8000/api/v1",
  "admin_token": "your-admin-token",
  "user_token": "your-user-token"
}

// Common test cases
const testCases = [
  'GET /themes/categories?type=website',
  'GET /themes/search?query=modern&type=website',
  'POST /themes/select (with auth)',
  'GET /admin/categories (admin auth)',
  'POST /admin/themes (with image upload)'
];
```

### Mock Data for Development
```javascript
// Theme mock data structure
const mockTheme = {
  id: 1,
  name: "Modern Blue",
  price: 299000,
  preview: "Clean modern design",
  preview_image: "/mock/preview-1.jpg",
  thumbnail_image: "/mock/thumb-1.jpg",
  demo_url: "https://demo.example.com/modern-blue",
  features: ["Responsive", "SEO Optimized"],
  category: {
    id: 1,
    name: "Modern Wedding",
    type: "website"
  }
};
```

---

## 🚀 Deployment Checklist

### Environment Configuration
- [ ] API base URLs configured for each environment
- [ ] Authentication tokens properly managed
- [ ] Image CDN endpoints configured
- [ ] Demo URLs accessible
- [ ] Error tracking service integrated

### Performance Optimization
- [ ] Image lazy loading implemented
- [ ] API response caching configured
- [ ] Search debouncing implemented
- [ ] Bundle optimization for theme assets

### User Experience
- [ ] Loading states for all API calls
- [ ] Error handling with user-friendly messages
- [ ] Mobile-responsive theme browsing
- [ ] Demo preview functionality working
- [ ] Theme selection flow tested

### Analytics Integration
- [ ] Theme interaction tracking
- [ ] Search behavior analytics
- [ ] Performance monitoring
- [ ] Error tracking and alerting

---

This completes the comprehensive API contract documentation for the Horuzt theme management system. Frontend developers now have complete specifications for implementing all features across admin management, user browsing, website themes, and video themes with proper integration guidelines, error handling, and performance optimization strategies.
