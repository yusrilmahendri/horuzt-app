# API Contract: Website Invitation Themes

## Overview
This document provides specialized API contracts for Website Invitation themes in the Horuzt wedding invitation system. Website invitations are interactive web pages that guests access via URL, featuring scrolling layouts, interactive elements, and responsive design.

## Business Context
Website invitations are digital wedding invitations that:
- Create a dedicated wedding website for the couple
- Allow guests to RSVP online
- Display wedding information, photos, and stories
- Support interactive features like music, animations, and guest books
- Are mobile-responsive and SEO-optimized

## Layout Types for Website Themes
- **Scroll**: Single-page scrolling experience with smooth animations
- **Slide**: Multi-section sliding navigation
- **Mobile**: Mobile-first design with touch interactions

---

## 🌐 Website Theme Categories

### 1. Get Website Theme Categories
**Endpoint**: `GET /themes/categories?type=website`
**Purpose**: Browse all website invitation theme categories
**Business Logic**: Only returns categories with active website themes

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
        "description": "Contemporary and sleek website designs",
        "icon": "modern-icon.svg",
        "sort_order": 1,
        "jenisThemas": [
          {
            "id": 1,
            "name": "Modern Blue",
            "price": 299000,
            "preview": "Clean modern design with blue accents",
            "preview_image": "/storage/themes/1/preview.jpg",
            "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/modern-blue",
            "features": [
              "Responsive Design",
              "Smooth Scrolling",
              "Photo Gallery",
              "RSVP Form",
              "Music Player",
              "Guest Book",
              "Location Maps"
            ],
            "description": "Perfect for modern couples who want a clean, sophisticated online presence"
          }
        ]
      },
      {
        "id": 2,
        "name": "Romantic Classic",
        "slug": "romantic-classic",
        "type": "website",
        "description": "Timeless and elegant romantic designs",
        "icon": "romantic-icon.svg",
        "sort_order": 2,
        "jenisThemas": [
          {
            "id": 5,
            "name": "Rose Garden",
            "price": 399000,
            "preview": "Elegant rose-themed romantic design",
            "preview_image": "/storage/themes/5/preview.jpg",
            "thumbnail_image": "/storage/themes/5/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/rose-garden",
            "features": [
              "Romantic Animations",
              "Floral Elements",
              "Elegant Typography",
              "Photo Slideshow",
              "Love Story Timeline",
              "Guest Wishes"
            ]
          }
        ]
      },
      {
        "id": 3,
        "name": "Minimalist",
        "slug": "minimalist",
        "type": "website",
        "description": "Clean and simple website designs",
        "icon": "minimal-icon.svg",
        "sort_order": 3,
        "jenisThemas": [
          {
            "id": 8,
            "name": "Pure White",
            "price": 249000,
            "preview": "Clean minimalist design with white theme",
            "preview_image": "/storage/themes/8/preview.jpg",
            "thumbnail_image": "/storage/themes/8/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/pure-white",
            "features": [
              "Minimalist Design",
              "Fast Loading",
              "Clean Typography",
              "Subtle Animations",
              "Mobile First"
            ]
          }
        ]
      }
    ],
    "total_categories": 8,
    "total_themes": 35
  }
}
```

---

## 📱 Website Layout-Based Browsing

### 1. Scroll Layout Themes
**Endpoint**: `GET /themes/layout?layout=Scroll&type=website`
**Purpose**: Get website themes optimized for scrolling experience
**Business Logic**: Themes designed for single-page scrolling with smooth animations

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Scroll",
    "type": "website",
    "themes": [
      {
        "id": 1,
        "name": "Modern Blue Scroll",
        "price": 299000,
        "preview": "Smooth scrolling modern design",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "features": [
          "Parallax Scrolling",
          "Smooth Animations",
          "Section Navigation",
          "Scroll Indicators",
          "Mobile Responsive"
        ],
        "description": "Beautiful scrolling experience with parallax effects",
        "layout_specifications": {
          "scroll_type": "parallax",
          "navigation_style": "sticky_menu",
          "animation_style": "fade_in_up",
          "mobile_scroll": "optimized"
        },
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website"
        }
      }
    ],
    "total": 12
  }
}
```

### 2. Slide Layout Themes
**Endpoint**: `GET /themes/layout?layout=Slide&type=website`
**Purpose**: Get website themes with sliding navigation
**Business Logic**: Multi-section themes with slide transitions

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Slide",
    "type": "website",
    "themes": [
      {
        "id": 15,
        "name": "Elegant Slides",
        "price": 349000,
        "preview": "Multi-section sliding website",
        "preview_image": "/storage/themes/15/preview.jpg",
        "thumbnail_image": "/storage/themes/15/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/elegant-slides",
        "features": [
          "Slide Navigation",
          "Section Transitions",
          "Touch Gestures",
          "Keyboard Navigation",
          "Progress Indicator"
        ],
        "description": "Multi-section website with beautiful slide transitions",
        "layout_specifications": {
          "slide_style": "horizontal",
          "transition_effect": "slide_fade",
          "navigation_dots": true,
          "swipe_enabled": true,
          "auto_advance": false
        },
        "category": {
          "id": 2,
          "name": "Romantic Classic",
          "type": "website"
        }
      }
    ],
    "total": 8
  }
}
```

### 3. Mobile-First Layout Themes
**Endpoint**: `GET /themes/layout?layout=Mobile&type=website`
**Purpose**: Get website themes optimized for mobile-first experience
**Business Logic**: Themes designed primarily for mobile viewing

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Mobile",
    "type": "website",
    "themes": [
      {
        "id": 22,
        "name": "Mobile First",
        "price": 199000,
        "preview": "Optimized for mobile viewing",
        "preview_image": "/storage/themes/22/preview.jpg",
        "thumbnail_image": "/storage/themes/22/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/mobile-first",
        "features": [
          "Mobile Optimized",
          "Touch Friendly",
          "Fast Loading",
          "Thumb Navigation",
          "Offline Support"
        ],
        "description": "Perfect for guests who primarily use mobile devices",
        "layout_specifications": {
          "mobile_first": true,
          "touch_optimized": true,
          "loading_speed": "ultra_fast",
          "offline_mode": true,
          "thumb_navigation": true
        },
        "category": {
          "id": 3,
          "name": "Minimalist",
          "type": "website"
        }
      }
    ],
    "total": 6
  }
}
```

---

## 🔍 Website Theme Search & Filtering

### Advanced Website Theme Search
**Endpoint**: `GET /themes/search?type=website&query=modern&layout=Scroll&price_max=400000`
**Purpose**: Search website themes with comprehensive filters

#### Request Parameters
```json
{
  "type": "website",
  "query": "modern",
  "category_id": 1,
  "layout": "Scroll|Slide|Mobile",
  "price_min": 0,
  "price_max": 400000,
  "features": ["RSVP Form", "Photo Gallery"],
  "mobile_optimized": true,
  "music_support": true,
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
      "layout": "Scroll",
      "price_range": [0, 400000],
      "features": ["RSVP Form", "Photo Gallery"],
      "mobile_optimized": true
    },
    "themes": [
      {
        "id": 1,
        "name": "Modern Blue Scroll",
        "price": 299000,
        "preview": "Clean modern scrolling design",
        "preview_image": "/storage/themes/1/preview.jpg",
        "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/modern-blue",
        "features": [
          "RSVP Form",
          "Photo Gallery",
          "Parallax Scrolling",
          "Mobile Responsive",
          "Music Player"
        ],
        "website_features": {
          "responsive_design": true,
          "seo_optimized": true,
          "loading_speed": "fast",
          "mobile_score": 95,
          "accessibility_score": 88
        },
        "category": {
          "id": 1,
          "name": "Modern Wedding",
          "type": "website"
        }
      }
    ],
    "total": 8,
    "facets": {
      "price_ranges": [
        {"range": "0-250000", "count": 12},
        {"range": "250000-400000", "count": 15},
        {"range": "400000+", "count": 8}
      ],
      "layouts": [
        {"layout": "Scroll", "count": 20},
        {"layout": "Slide", "count": 10},
        {"layout": "Mobile", "count": 5}
      ],
      "popular_features": [
        {"feature": "RSVP Form", "count": 30},
        {"feature": "Photo Gallery", "count": 28},
        {"feature": "Music Player", "count": 25}
      ]
    }
  }
}
```

---

## 🎨 Website Theme Details & Specifications

### Get Detailed Website Theme Information
**Endpoint**: `GET /themes/theme/{themeId}`
**Purpose**: Get comprehensive details for a website theme

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "id": 1,
    "name": "Modern Blue Scroll",
    "price": 299000,
    "preview": "Clean modern scrolling design with blue accents",
    "preview_image": "/storage/themes/1/preview.jpg",
    "thumbnail_image": "/storage/themes/1/thumbnail.jpg",
    "demo_url": "https://demo.horuzt.com/modern-blue",
    "url_thema": "https://themes.horuzt.com/modern-blue",
    "description": "A sophisticated modern website theme perfect for contemporary couples who want a clean, professional online wedding invitation.",
    "features": [
      "Responsive Design",
      "Parallax Scrolling",
      "Photo Gallery",
      "RSVP Form",
      "Music Player",
      "Guest Book",
      "Location Maps",
      "Social Sharing",
      "SEO Optimized"
    ],
    "category": {
      "id": 1,
      "name": "Modern Wedding",
      "type": "website",
      "slug": "modern-wedding"
    },
    "website_specifications": {
      "layout_type": "Scroll",
      "design_style": "Modern",
      "color_scheme": "Blue & White",
      "typography": "Montserrat, Sans-serif",
      "animations": "Smooth & Subtle",
      "loading_speed": "Fast (< 3s)",
      "mobile_responsive": true,
      "seo_optimized": true,
      "accessibility_compliant": true
    },
    "included_sections": [
      {
        "name": "Hero Section",
        "description": "Beautiful header with couple names and wedding date",
        "features": ["Background Image", "Countdown Timer", "Scroll Indicator"]
      },
      {
        "name": "Our Story",
        "description": "Timeline of the couple's relationship",
        "features": ["Photo Timeline", "Story Text", "Romantic Animations"]
      },
      {
        "name": "Wedding Details",
        "description": "Ceremony and reception information",
        "features": ["Date & Time", "Venue Information", "Google Maps Integration"]
      },
      {
        "name": "Photo Gallery",
        "description": "Beautiful photo showcase",
        "features": ["Lightbox Gallery", "Lazy Loading", "Mobile Swipe"]
      },
      {
        "name": "RSVP Form",
        "description": "Guest response collection",
        "features": ["Online Form", "Meal Preferences", "Plus-One Options"]
      },
      {
        "name": "Guest Book",
        "description": "Wishes and messages from guests",
        "features": ["Public Messages", "Moderation", "Photo Uploads"]
      }
    ],
    "customization_options": {
      "colors": ["Primary Color", "Secondary Color", "Text Color"],
      "fonts": ["Heading Font", "Body Font"],
      "images": ["Background Images", "Gallery Photos", "Couple Photos"],
      "content": ["All Text Content", "Wedding Information", "Story Timeline"],
      "music": ["Background Music", "Auto-play Options", "Volume Control"]
    },
    "technical_details": {
      "page_size": "< 2MB",
      "loading_time": "< 3 seconds",
      "mobile_score": 95,
      "accessibility_score": 88,
      "seo_score": 92,
      "browser_support": ["Chrome", "Firefox", "Safari", "Edge", "Mobile Browsers"]
    }
  }
}
```

---

## 🚀 Website Theme Demo & Preview

### Get Website Theme Demo
**Endpoint**: `GET /themes/demo/{themeId}`
**Purpose**: Access live demo of website theme

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "theme_id": 1,
    "theme_name": "Modern Blue Scroll",
    "demo_url": "https://demo.horuzt.com/modern-blue",
    "demo_features": {
      "interactive": true,
      "sample_content": true,
      "all_sections": true,
      "mobile_preview": true
    },
    "demo_credentials": {
      "required": false,
      "guest_access": true
    },
    "preview_options": {
      "desktop_view": "https://demo.horuzt.com/modern-blue",
      "mobile_view": "https://demo.horuzt.com/modern-blue?view=mobile",
      "tablet_view": "https://demo.horuzt.com/modern-blue?view=tablet"
    }
  }
}
```

---

## 💡 Website Theme Selection Process

### Business Logic for Website Theme Selection
1. **Compatibility Check**: Verify theme works with user's package
2. **Feature Validation**: Ensure user's package includes theme features
3. **Mobile Compatibility**: Confirm mobile optimization if required
4. **SEO Requirements**: Check if SEO features are needed

### Select Website Theme
**Endpoint**: `POST /themes/select`
**Authentication**: Required

#### Request Body
```json
{
  "theme_id": 1,
  "customization_preferences": {
    "primary_color": "#2563eb",
    "secondary_color": "#f8fafc",
    "font_style": "modern",
    "layout_preference": "scroll"
  }
}
```

#### Response Success (200)
```json
{
  "status": true,
  "message": "Website theme selected successfully",
  "data": {
    "selection": {
      "id": 123,
      "user_id": 456,
      "theme_id": 1,
      "theme_type": "website",
      "selected_at": "2025-01-01T12:00:00Z",
      "customization_data": {
        "primary_color": "#2563eb",
        "secondary_color": "#f8fafc",
        "font_style": "modern",
        "layout_preference": "scroll"
      }
    },
    "theme": {
      "id": 1,
      "name": "Modern Blue Scroll",
      "type": "website",
      "category": "Modern Wedding"
    },
    "next_steps": {
      "setup_url": "/dashboard/website-setup",
      "customization_url": "/dashboard/website-customize",
      "preview_url": "/preview/website"
    }
  }
}
```

---

## 📊 Website Theme Analytics & Insights

### Popular Website Features
**Endpoint**: `GET /themes/analytics/features?type=website`
**Purpose**: Get most requested website features

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "popular_features": [
      {
        "feature": "RSVP Form",
        "usage_percentage": 95,
        "user_satisfaction": 4.8,
        "description": "Essential for guest management"
      },
      {
        "feature": "Photo Gallery",
        "usage_percentage": 92,
        "user_satisfaction": 4.7,
        "description": "Showcase couple's memories"
      },
      {
        "feature": "Music Player",
        "usage_percentage": 78,
        "user_satisfaction": 4.5,
        "description": "Background music for ambiance"
      },
      {
        "feature": "Guest Book",
        "usage_percentage": 65,
        "user_satisfaction": 4.3,
        "description": "Collect wishes from guests"
      }
    ],
    "layout_preferences": [
      {"layout": "Scroll", "percentage": 60},
      {"layout": "Slide", "percentage": 30},
      {"layout": "Mobile", "percentage": 10}
    ]
  }
}
```

---

## 🛠️ Frontend Implementation Guidelines

### Website Theme Component Structure
```javascript
const WebsiteThemeCard = {
  // Basic Info
  id: 1,
  name: "Modern Blue Scroll",
  price: 299000,
  category: "Modern Wedding",
  
  // Visual Assets
  thumbnail_image: "/storage/themes/1/thumbnail.jpg",
  preview_image: "/storage/themes/1/preview.jpg",
  demo_url: "https://demo.horuzt.com/modern-blue",
  
  // Website-Specific Info
  layout_type: "Scroll",
  mobile_optimized: true,
  seo_optimized: true,
  loading_speed: "Fast",
  
  // Features Array
  features: [
    "Responsive Design",
    "RSVP Form", 
    "Photo Gallery",
    "Music Player"
  ],
  
  // Selection State
  is_selected: false,
  is_popular: true,
  compatibility: "full" // full, partial, incompatible
};
```

### Website Theme Demo Integration
```javascript
// Open theme demo in new window
function openWebsiteDemo(themeId) {
  const demoResponse = await fetch(`/themes/demo/${themeId}`);
  const demoData = await demoResponse.json();
  
  if (demoData.status) {
    // Open in new window with specific dimensions for website preview
    window.open(
      demoData.data.demo_url, 
      'theme-demo',
      'width=1200,height=800,scrollbars=yes,resizable=yes'
    );
  }
}

// Mobile preview option
function openMobilePreview(themeId) {
  const demoUrl = `https://demo.horuzt.com/theme-${themeId}?view=mobile`;
  window.open(demoUrl, 'mobile-demo', 'width=375,height=667');
}
```

### Website Theme Selection Flow
```javascript
// Complete website theme selection process
async function selectWebsiteTheme(themeId, customizations = {}) {
  try {
    const response = await fetch('/themes/select', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        theme_id: themeId,
        customization_preferences: customizations
      })
    });
    
    const result = await response.json();
    
    if (result.status) {
      // Redirect to website setup
      window.location.href = result.data.next_steps.setup_url;
    }
  } catch (error) {
    console.error('Theme selection failed:', error);
  }
}
```

---

## 🎯 Business Rules for Website Themes

### Theme Compatibility Rules
- **Basic Package**: Access to Mobile and simple Scroll themes only
- **Premium Package**: Access to all layout types and advanced features
- **Enterprise Package**: Full customization and white-label options

### Feature Requirements
- **RSVP Form**: Included in all packages, connected to guest management
- **Photo Gallery**: Basic (10 photos) in basic, unlimited in premium
- **Music Player**: Basic package (1 song), premium (unlimited)
- **Custom Domain**: Premium package and above only

### SEO & Performance Standards
- **Loading Speed**: Must be under 3 seconds on mobile
- **Mobile Score**: Minimum 85/100 for mobile optimization
- **Accessibility**: WCAG 2.1 AA compliance required
- **SEO**: Meta tags, structured data, sitemap generation

---

This completes the Website Invitation theme API contract. Frontend developers can use this to build a comprehensive website theme browsing, preview, and selection experience.
