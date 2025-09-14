# API Contract: Video Invitation Themes

## Overview
This document provides specialized API contracts for Video Invitation themes in the Horuzt wedding invitation system. Video invitations are digital video presentations that can be shared via social media, messaging apps, or embedded in websites, featuring cinematic storytelling, photo slideshows, and professional video editing.

## Business Context
Video invitations are multimedia wedding invitations that:
- Create professional video presentations for the couple
- Combine photos, videos, music, and text in cinematic style
- Can be shared across social platforms (WhatsApp, Instagram, Facebook)
- Support both animated slideshows and full video productions
- Include interactive elements and call-to-action buttons

## Layout Types for Video Themes
- **Cinematic**: Professional movie-style video presentations
- **Slide**: Photo slideshow with transitions and music
- **Mobile**: Vertical format optimized for mobile sharing
- **Social**: Square or story format for social media platforms

---

## 🎬 Video Theme Categories

### 1. Get Video Theme Categories
**Endpoint**: `GET /themes/categories?type=video`
**Purpose**: Browse all video invitation theme categories
**Business Logic**: Only returns categories with active video themes

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
        "description": "Professional movie-style video invitations",
        "icon": "cinematic-icon.svg",
        "sort_order": 1,
        "jenisThemas": [
          {
            "id": 25,
            "name": "Elegant Cinematic",
            "price": 599000,
            "preview": "Professional cinematic video template",
            "preview_image": "/storage/themes/25/preview.jpg",
            "thumbnail_image": "/storage/themes/25/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/elegant-cinematic",
            "features": [
              "4K Video Quality",
              "Professional Editing",
              "Background Music",
              "Text Animations",
              "Photo Integration",
              "Duration: 60-90s",
              "Multiple Formats"
            ],
            "description": "High-end cinematic video invitation with professional editing and 4K quality"
          }
        ]
      },
      {
        "id": 11,
        "name": "Romantic Slideshow",
        "slug": "romantic-slideshow",
        "type": "video",
        "description": "Beautiful photo slideshows with romantic themes",
        "icon": "slideshow-icon.svg",
        "sort_order": 2,
        "jenisThemas": [
          {
            "id": 28,
            "name": "Rose Garden Slides",
            "price": 399000,
            "preview": "Romantic photo slideshow with rose theme",
            "preview_image": "/storage/themes/28/preview.jpg",
            "thumbnail_image": "/storage/themes/28/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/rose-garden-slides",
            "features": [
              "Photo Slideshow",
              "Romantic Music",
              "Smooth Transitions",
              "Text Overlays",
              "Duration: 30-60s",
              "HD Quality"
            ]
          }
        ]
      },
      {
        "id": 12,
        "name": "Social Media Ready",
        "slug": "social-media-ready",
        "type": "video",
        "description": "Videos optimized for social media sharing",
        "icon": "social-icon.svg",
        "sort_order": 3,
        "jenisThemas": [
          {
            "id": 32,
            "name": "Instagram Story",
            "price": 299000,
            "preview": "Vertical video perfect for Instagram stories",
            "preview_image": "/storage/themes/32/preview.jpg",
            "thumbnail_image": "/storage/themes/32/thumbnail.jpg",
            "demo_url": "https://demo.horuzt.com/instagram-story",
            "features": [
              "Vertical Format",
              "15-30s Duration",
              "Social Optimized",
              "Quick Sharing",
              "Mobile First"
            ]
          }
        ]
      }
    ],
    "total_categories": 5,
    "total_themes": 18
  }
}
```

---

## 🎥 Video Layout-Based Browsing

### 1. Cinematic Layout Themes
**Endpoint**: `GET /themes/layout?layout=Cinematic&type=video`
**Purpose**: Get professional cinematic video themes
**Business Logic**: High-end video productions with professional editing

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Cinematic",
    "type": "video",
    "themes": [
      {
        "id": 25,
        "name": "Elegant Cinematic",
        "price": 599000,
        "preview": "Professional movie-style video invitation",
        "preview_image": "/storage/themes/25/preview.jpg",
        "thumbnail_image": "/storage/themes/25/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/elegant-cinematic",
        "features": [
          "4K Video Quality",
          "Professional Color Grading",
          "Cinematic Transitions",
          "Orchestral Music",
          "Title Sequences",
          "Credit Roll"
        ],
        "description": "Hollywood-style wedding video invitation with professional production values",
        "video_specifications": {
          "resolution": "4K (3840x2160)",
          "duration": "60-90 seconds",
          "format": "MP4, MOV",
          "aspect_ratio": "16:9",
          "frame_rate": "24fps (cinematic)",
          "audio_quality": "48kHz 16-bit",
          "file_size": "50-100MB"
        },
        "production_details": {
          "editing_style": "Professional",
          "color_treatment": "Cinematic grading",
          "music_style": "Orchestral/Epic",
          "text_animation": "Professional titles",
          "delivery_time": "3-5 business days"
        },
        "category": {
          "id": 10,
          "name": "Cinematic Wedding",
          "type": "video"
        }
      }
    ],
    "total": 6
  }
}
```

### 2. Slideshow Layout Themes
**Endpoint**: `GET /themes/layout?layout=Slide&type=video`
**Purpose**: Get photo slideshow video themes
**Business Logic**: Photo-based videos with transitions and music

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
        "name": "Romantic Photo Story",
        "price": 399000,
        "preview": "Beautiful photo slideshow with romantic music",
        "preview_image": "/storage/themes/28/preview.jpg",
        "thumbnail_image": "/storage/themes/28/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/romantic-photo-story",
        "features": [
          "Photo Slideshow",
          "Ken Burns Effect",
          "Romantic Music",
          "Text Overlays",
          "Fade Transitions",
          "Timeline Support"
        ],
        "description": "Transform your photos into a beautiful romantic video story",
        "video_specifications": {
          "resolution": "Full HD (1920x1080)",
          "duration": "30-60 seconds",
          "format": "MP4",
          "aspect_ratio": "16:9, 1:1, 9:16",
          "frame_rate": "30fps",
          "audio_quality": "44.1kHz 16-bit",
          "file_size": "20-40MB"
        },
        "slideshow_features": {
          "max_photos": 20,
          "transition_effects": ["Fade", "Slide", "Zoom", "Ken Burns"],
          "text_support": true,
          "music_sync": true,
          "auto_timing": true,
          "manual_timing": true
        },
        "category": {
          "id": 11,
          "name": "Romantic Slideshow",
          "type": "video"
        }
      }
    ],
    "total": 8
  }
}
```

### 3. Mobile Vertical Layout Themes
**Endpoint**: `GET /themes/layout?layout=Mobile&type=video`
**Purpose**: Get mobile-optimized vertical video themes
**Business Logic**: Vertical format videos for mobile sharing

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "layout": "Mobile",
    "type": "video",
    "themes": [
      {
        "id": 32,
        "name": "Mobile Story",
        "price": 299000,
        "preview": "Vertical video perfect for mobile sharing",
        "preview_image": "/storage/themes/32/preview.jpg",
        "thumbnail_image": "/storage/themes/32/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/mobile-story",
        "features": [
          "Vertical Format",
          "Mobile Optimized",
          "Quick Loading",
          "Social Ready",
          "Touch Friendly",
          "WhatsApp Ready"
        ],
        "description": "Perfect for sharing on WhatsApp, Instagram Stories, and TikTok",
        "video_specifications": {
          "resolution": "1080x1920 (9:16)",
          "duration": "15-30 seconds",
          "format": "MP4",
          "aspect_ratio": "9:16",
          "frame_rate": "30fps",
          "audio_quality": "44.1kHz 16-bit",
          "file_size": "10-25MB"
        },
        "mobile_features": {
          "vertical_optimized": true,
          "thumb_friendly": true,
          "quick_share": true,
          "small_file_size": true,
          "data_efficient": true
        },
        "category": {
          "id": 12,
          "name": "Social Media Ready",
          "type": "video"
        }
      }
    ],
    "total": 4
  }
}
```

---

## 🔍 Video Theme Search & Filtering

### Advanced Video Theme Search
**Endpoint**: `GET /themes/search?type=video&query=cinematic&duration=60-90&format=4K`
**Purpose**: Search video themes with video-specific filters

#### Request Parameters
```json
{
  "type": "video",
  "query": "cinematic",
  "category_id": 10,
  "layout": "Cinematic|Slide|Mobile|Social",
  "price_min": 0,
  "price_max": 800000,
  "duration": "15-30|30-60|60-90|90+",
  "resolution": "HD|Full HD|4K",
  "aspect_ratio": "16:9|1:1|9:16",
  "features": ["4K Quality", "Professional Editing"],
  "music_style": "Romantic|Epic|Modern|Classical",
  "limit": 15
}
```

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "query": "cinematic",
    "filters": {
      "type": "video",
      "layout": "Cinematic",
      "duration": "60-90",
      "resolution": "4K",
      "price_range": [0, 800000]
    },
    "themes": [
      {
        "id": 25,
        "name": "Elegant Cinematic",
        "price": 599000,
        "preview": "Professional cinematic video template",
        "preview_image": "/storage/themes/25/preview.jpg",
        "thumbnail_image": "/storage/themes/25/thumbnail.jpg",
        "demo_url": "https://demo.horuzt.com/elegant-cinematic",
        "features": [
          "4K Video Quality",
          "Professional Editing",
          "Cinematic Music",
          "Title Sequences"
        ],
        "video_specs": {
          "resolution": "4K",
          "duration": "60-90s",
          "aspect_ratio": "16:9",
          "quality": "Professional"
        },
        "category": {
          "id": 10,
          "name": "Cinematic Wedding",
          "type": "video"
        }
      }
    ],
    "total": 5,
    "video_facets": {
      "resolutions": [
        {"resolution": "HD", "count": 8},
        {"resolution": "Full HD", "count": 12},
        {"resolution": "4K", "count": 5}
      ],
      "durations": [
        {"duration": "15-30s", "count": 6},
        {"duration": "30-60s", "count": 10},
        {"duration": "60-90s", "count": 8}
      ],
      "aspect_ratios": [
        {"ratio": "16:9", "count": 15},
        {"ratio": "1:1", "count": 6},
        {"ratio": "9:16", "count": 4}
      ]
    }
  }
}
```

---

## 🎬 Video Theme Details & Specifications

### Get Detailed Video Theme Information
**Endpoint**: `GET /themes/theme/{themeId}` (for video themes)
**Purpose**: Get comprehensive details for a video theme

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "id": 25,
    "name": "Elegant Cinematic",
    "price": 599000,
    "preview": "Professional cinematic video invitation template",
    "preview_image": "/storage/themes/25/preview.jpg",
    "thumbnail_image": "/storage/themes/25/thumbnail.jpg",
    "demo_url": "https://demo.horuzt.com/elegant-cinematic",
    "url_thema": "https://themes.horuzt.com/elegant-cinematic",
    "description": "Create a Hollywood-style wedding video invitation with professional cinematic quality, perfect for couples who want a luxurious and sophisticated video presentation.",
    "features": [
      "4K Video Quality",
      "Professional Color Grading",
      "Cinematic Transitions",
      "Orchestral Background Music",
      "Professional Title Sequences",
      "Credit Roll",
      "Multiple Export Formats",
      "Social Media Versions"
    ],
    "category": {
      "id": 10,
      "name": "Cinematic Wedding",
      "type": "video",
      "slug": "cinematic-wedding"
    },
    "video_specifications": {
      "resolution": "4K (3840x2160)",
      "duration": "60-90 seconds",
      "format": ["MP4", "MOV", "WebM"],
      "aspect_ratios": ["16:9", "1:1", "9:16"],
      "frame_rate": "24fps (cinematic)",
      "bitrate": "50-100 Mbps",
      "audio_quality": "48kHz 16-bit stereo",
      "color_space": "Rec. 709",
      "file_sizes": {
        "4K": "80-120MB",
        "Full HD": "40-60MB",
        "HD": "20-30MB",
        "Mobile": "10-20MB"
      }
    },
    "production_details": {
      "editing_style": "Professional cinematic",
      "color_treatment": "Cinematic color grading",
      "music_included": true,
      "music_style": "Orchestral/Epic",
      "voice_over_support": true,
      "subtitle_support": true,
      "delivery_formats": ["Cinema 4K", "Full HD", "Mobile HD", "Social Media"],
      "production_time": "3-5 business days",
      "revisions_included": 2
    },
    "customization_options": {
      "personal_photos": "Up to 15 photos",
      "personal_videos": "Up to 3 short clips (max 10s each)",
      "text_elements": ["Names", "Wedding Date", "Venue", "Custom Message"],
      "music_options": ["Provided tracks", "Upload custom music"],
      "color_schemes": ["Classic", "Warm", "Cool", "Vintage", "Modern"],
      "title_styles": ["Elegant", "Bold", "Script", "Modern"],
      "duration_options": ["60s", "75s", "90s"]
    },
    "included_elements": [
      {
        "element": "Opening Title Sequence",
        "description": "Professional animated title with couple names",
        "duration": "5-8 seconds"
      },
      {
        "element": "Photo Montage",
        "description": "Cinematic presentation of couple's photos",
        "duration": "30-45 seconds"
      },
      {
        "element": "Wedding Details",
        "description": "Animated text with wedding information",
        "duration": "10-15 seconds"
      },
      {
        "element": "Call to Action",
        "description": "RSVP or website information",
        "duration": "5-8 seconds"
      },
      {
        "element": "Closing Credits",
        "description": "Elegant ending with contact details",
        "duration": "5-10 seconds"
      }
    ],
    "export_options": {
      "cinema_quality": {
        "resolution": "4K",
        "use_case": "Big screen display",
        "file_size": "100-150MB"
      },
      "web_quality": {
        "resolution": "Full HD",
        "use_case": "Website embedding",
        "file_size": "30-50MB"
      },
      "mobile_quality": {
        "resolution": "720p",
        "use_case": "WhatsApp sharing",
        "file_size": "15-25MB"
      },
      "social_media": {
        "instagram_feed": "1080x1080",
        "instagram_story": "1080x1920",
        "facebook_video": "1280x720",
        "whatsapp_status": "1080x1920"
      }
    }
  }
}
```

---

## 🎞️ Video Theme Demo & Preview

### Get Video Theme Demo
**Endpoint**: `GET /themes/demo/{themeId}` (for video themes)
**Purpose**: Access video demo with playback options

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "theme_id": 25,
    "theme_name": "Elegant Cinematic",
    "demo_url": "https://demo.horuzt.com/elegant-cinematic",
    "video_demos": {
      "full_quality": {
        "url": "https://demo.horuzt.com/videos/elegant-cinematic-4k.mp4",
        "resolution": "4K",
        "file_size": "85MB",
        "duration": "75s"
      },
      "preview_quality": {
        "url": "https://demo.horuzt.com/videos/elegant-cinematic-preview.mp4",
        "resolution": "720p",
        "file_size": "15MB",
        "duration": "75s"
      },
      "mobile_preview": {
        "url": "https://demo.horuzt.com/videos/elegant-cinematic-mobile.mp4",
        "resolution": "480p",
        "file_size": "8MB",
        "duration": "75s"
      }
    },
    "preview_features": {
      "autoplay": false,
      "controls": true,
      "muted_start": true,
      "loop": false,
      "subtitles_available": false
    },
    "sample_content": {
      "uses_placeholder": true,
      "placeholder_names": "John & Jane",
      "placeholder_date": "June 15, 2025",
      "placeholder_venue": "Beautiful Garden Resort"
    }
  }
}
```

---

## 💎 Video Theme Selection Process

### Business Logic for Video Theme Selection
1. **Production Requirements**: Verify user has required assets (photos, videos)
2. **Package Compatibility**: Check if user's package includes video production
3. **Timeline Validation**: Confirm production timeline meets wedding date
4. **Asset Quality Check**: Ensure provided assets meet minimum quality standards

### Select Video Theme
**Endpoint**: `POST /themes/select`
**Authentication**: Required

#### Request Body
```json
{
  "theme_id": 25,
  "video_preferences": {
    "duration": "75s",
    "resolution": "4K",
    "aspect_ratio": "16:9",
    "music_style": "orchestral",
    "color_scheme": "warm",
    "title_style": "elegant"
  },
  "content_assets": {
    "photos_count": 12,
    "videos_count": 2,
    "custom_music": false,
    "voice_over_needed": false
  }
}
```

#### Response Success (200)
```json
{
  "status": true,
  "message": "Video theme selected successfully",
  "data": {
    "selection": {
      "id": 124,
      "user_id": 456,
      "theme_id": 25,
      "theme_type": "video",
      "selected_at": "2025-01-01T12:00:00Z",
      "video_preferences": {
        "duration": "75s",
        "resolution": "4K",
        "aspect_ratio": "16:9",
        "music_style": "orchestral",
        "color_scheme": "warm",
        "title_style": "elegant"
      },
      "production_status": "assets_pending"
    },
    "theme": {
      "id": 25,
      "name": "Elegant Cinematic",
      "type": "video",
      "category": "Cinematic Wedding"
    },
    "next_steps": {
      "upload_assets_url": "/dashboard/video-assets",
      "production_timeline": "3-5 business days",
      "asset_requirements": {
        "photos": "8-15 high-quality photos (min 1080p)",
        "videos": "Optional: 1-3 short clips (max 10s each)",
        "text_content": "Names, date, venue, custom message"
      }
    },
    "production_schedule": {
      "asset_submission_deadline": "2025-01-08T23:59:59Z",
      "first_draft_delivery": "2025-01-12T00:00:00Z",
      "final_delivery": "2025-01-15T00:00:00Z",
      "revisions_allowed": 2
    }
  }
}
```

---

## 🏭 Video Production Workflow APIs

### Upload Assets for Video Production
**Endpoint**: `POST /video-production/assets`
**Purpose**: Upload photos and videos for theme customization

#### Request Body (multipart/form-data)
```json
{
  "selection_id": 124,
  "photos": "[FILES]", // Array of image files
  "videos": "[FILES]", // Array of video files (optional)
  "wedding_details": {
    "bride_name": "Jane Doe",
    "groom_name": "John Smith",
    "wedding_date": "2025-06-15",
    "venue_name": "Beautiful Garden Resort",
    "ceremony_time": "16:00",
    "custom_message": "Join us for our special day"
  },
  "music_preference": "provided", // or "custom"
  "custom_music_file": "[FILE]" // if music_preference is "custom"
}
```

### Get Production Status
**Endpoint**: `GET /video-production/status/{selectionId}`
**Purpose**: Check video production progress

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "selection_id": 124,
    "production_status": "in_progress",
    "current_stage": "editing",
    "progress_percentage": 65,
    "timeline": {
      "assets_received": "2025-01-08T14:30:00Z",
      "production_started": "2025-01-09T09:00:00Z",
      "estimated_completion": "2025-01-12T17:00:00Z"
    },
    "stages": [
      {
        "stage": "assets_review",
        "status": "completed",
        "completed_at": "2025-01-08T16:00:00Z"
      },
      {
        "stage": "editing",
        "status": "in_progress",
        "progress": 65,
        "estimated_completion": "2025-01-11T17:00:00Z"
      },
      {
        "stage": "review",
        "status": "pending"
      },
      {
        "stage": "final_delivery",
        "status": "pending"
      }
    ]
  }
}
```

---

## 📊 Video Theme Analytics

### Video Theme Performance Metrics
**Endpoint**: `GET /themes/analytics/video-performance`
**Purpose**: Get video theme usage and satisfaction data

#### Response Success (200)
```json
{
  "status": true,
  "data": {
    "popular_video_features": [
      {
        "feature": "4K Quality",
        "usage_percentage": 75,
        "satisfaction_score": 4.8,
        "price_impact": "+40%"
      },
      {
        "feature": "Professional Editing",
        "usage_percentage": 90,
        "satisfaction_score": 4.9,
        "price_impact": "+60%"
      },
      {
        "feature": "Custom Music",
        "usage_percentage": 45,
        "satisfaction_score": 4.6,
        "price_impact": "+20%"
      }
    ],
    "layout_preferences": [
      {"layout": "Cinematic", "percentage": 45},
      {"layout": "Slide", "percentage": 35},
      {"layout": "Mobile", "percentage": 15},
      {"layout": "Social", "percentage": 5}
    ],
    "duration_preferences": [
      {"duration": "30-60s", "percentage": 55},
      {"duration": "60-90s", "percentage": 35},
      {"duration": "15-30s", "percentage": 10}
    ],
    "resolution_choices": [
      {"resolution": "Full HD", "percentage": 60},
      {"resolution": "4K", "percentage": 30},
      {"resolution": "HD", "percentage": 10}
    ]
  }
}
```

---

## 🛠️ Frontend Implementation Guidelines

### Video Theme Component Structure
```javascript
const VideoThemeCard = {
  // Basic Info
  id: 25,
  name: "Elegant Cinematic",
  price: 599000,
  category: "Cinematic Wedding",
  
  // Visual Assets
  thumbnail_image: "/storage/themes/25/thumbnail.jpg",
  preview_image: "/storage/themes/25/preview.jpg",
  demo_url: "https://demo.horuzt.com/elegant-cinematic",
  
  // Video-Specific Info
  layout_type: "Cinematic",
  resolution: "4K",
  duration: "60-90s",
  aspect_ratio: "16:9",
  
  // Production Info
  production_time: "3-5 days",
  revisions_included: 2,
  
  // Features Array
  features: [
    "4K Video Quality",
    "Professional Editing",
    "Cinematic Music",
    "Title Sequences"
  ],
  
  // Video Specifications
  video_specs: {
    file_size: "80-120MB",
    formats: ["MP4", "MOV"],
    audio_quality: "48kHz 16-bit"
  },
  
  // Selection State
  is_selected: false,
  is_popular: true,
  production_complexity: "high"
};
```

### Video Demo Player Integration
```javascript
// Video demo player with quality options
function initVideoDemo(themeId) {
  const player = {
    element: document.getElementById('video-demo-player'),
    qualities: ['4K', 'Full HD', 'HD', 'Mobile'],
    currentQuality: 'Full HD',
    
    loadDemo: async function(quality = 'Full HD') {
      const response = await fetch(`/themes/demo/${themeId}`);
      const data = await response.json();
      
      let videoUrl;
      switch(quality) {
        case '4K':
          videoUrl = data.data.video_demos.full_quality.url;
          break;
        case 'Full HD':
          videoUrl = data.data.video_demos.preview_quality.url;
          break;
        default:
          videoUrl = data.data.video_demos.mobile_preview.url;
      }
      
      this.element.src = videoUrl;
      this.element.load();
    },
    
    changeQuality: function(newQuality) {
      this.currentQuality = newQuality;
      this.loadDemo(newQuality);
    }
  };
  
  return player;
}
```

### Video Theme Selection with Asset Upload
```javascript
// Complete video theme selection with asset planning
async function selectVideoTheme(themeId, preferences = {}) {
  try {
    // Step 1: Select theme
    const selectionResponse = await fetch('/themes/select', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        theme_id: themeId,
        video_preferences: preferences
      })
    });
    
    const selectionResult = await selectionResponse.json();
    
    if (selectionResult.status) {
      // Step 2: Redirect to asset upload
      const assetUploadUrl = selectionResult.data.next_steps.upload_assets_url;
      const requirements = selectionResult.data.next_steps.asset_requirements;
      
      // Show asset requirements modal
      showAssetRequirementsModal(requirements);
      
      // After user confirms, redirect to upload
      window.location.href = assetUploadUrl;
    }
  } catch (error) {
    console.error('Video theme selection failed:', error);
  }
}

// Asset upload handler
async function uploadVideoAssets(selectionId, assets) {
  const formData = new FormData();
  formData.append('selection_id', selectionId);
  
  // Add photos
  assets.photos.forEach((photo, index) => {
    formData.append(`photos[${index}]`, photo);
  });
  
  // Add videos if any
  if (assets.videos) {
    assets.videos.forEach((video, index) => {
      formData.append(`videos[${index}]`, video);
    });
  }
  
  // Add wedding details
  formData.append('wedding_details', JSON.stringify(assets.weddingDetails));
  
  const response = await fetch('/video-production/assets', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`
    },
    body: formData
  });
  
  return response.json();
}
```

---

## 🎯 Business Rules for Video Themes

### Production Requirements
- **Minimum Photos**: 8 photos (high resolution preferred)
- **Maximum Photos**: 20 photos (to maintain video flow)
- **Video Clips**: Optional, max 3 clips of 10 seconds each
- **Photo Quality**: Minimum 1080p, prefer 4K for best results

### Package Compatibility
- **Basic Package**: HD quality, slideshow themes only
- **Premium Package**: Full HD quality, all layouts except 4K cinematic
- **Enterprise Package**: 4K quality, all themes, custom music, voice-over

### Production Timeline
- **Asset Review**: 1 business day
- **Video Production**: 2-4 business days (depending on complexity)
- **Revisions**: 1 business day per revision
- **Final Delivery**: Same day after approval

### Quality Standards
- **Resolution**: Minimum 720p for mobile, up to 4K for premium
- **Duration**: Optimized for platform (15s for stories, 60-90s for full videos)
- **Audio**: Professional mixing and mastering included
- **Color**: Professional color grading for cinematic themes

---

This completes the Video Invitation theme API contract. Frontend developers can use this to build a comprehensive video theme browsing, demo viewing, selection, and production management experience.
