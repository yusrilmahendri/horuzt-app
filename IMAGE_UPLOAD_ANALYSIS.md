# Image Upload API Analysis - Horuzt App

## Storage Analysis

### Current Storage Usage
```
Total Storage: 21.22 MB
├── photos/            12 MB  (56.5%)
├── music/            8.7 MB  (41.0%)
└── profile-photos/   520 KB  (2.5%)
```

### File Count
- Total image files: 7 files
- Average size per image: ~1.7 MB

### File Details (photos/)
```
1QZukwFz1SWaREmpVDY1LrdM2Smd8q1xDvvNXZRN.png    200 KB
C6eDI6ofiyQDG3HbGUV9OPVtnKJ1AaD0aA2GrKZw.jpg    2.9 MB
fv9YNSYYi8I43QMnno2unE1Kp5n7PyApUsN5vW3t.jpg    2.9 MB
p0M2FLd8QcrGTDu5JRfYA7ypMIkBjYYiCO1hyvTt.jpg    2.9 MB
wiKXBnQR71NEmwUjlsiuctCKqDfL0gICUr7mFoce.jpg    2.9 MB
```

---

## API Endpoints with Image Upload

### 1. Gallery Upload API
**Endpoint:** `POST /api/v1/user/submission-galery`  
**Controller:** `GaleryController@store`  
**Middleware:** `large.files`, `bypass.post.size`

**Validation:**
```php
'photo' => 'required|file|mimes:jpg,png,jpeg|max:5222'
```

**Configuration:**
- Max size: 5.1 MB (5222 KB)
- Formats: JPG, PNG, JPEG
- Storage: `storage/app/public/photos/`
- Additional: url_video, nama_foto fields

**Issues Found:**
- Uses generic validation rule without proper dimensions check
- No image optimization or compression
- File size limit is high (5.1 MB per image)

---

### 2. Mempelai (Bride/Groom) Photos API
**Endpoint:** `POST /api/v1/user/update-mempelai`  
**Controller:** `MempelaiController@update`

**Validation:**
```php
'cover_photo'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
'photo_pria'     => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
'photo_wanita'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
```

**Configuration:**
- Max size: 2 MB (2048 KB) per photo
- Formats: JPEG, PNG, JPG, GIF
- Storage: `storage/app/public/photos/`
- 3 separate photo fields

**Issues Found:**
- No image optimization
- Uses same storage directory as gallery
- Max 2 MB is reasonable but no compression

---

### 3. Profile Photo Upload API
**Endpoints:**
- Admin: `POST /api/admin/profile/photo`
- User: `POST /api/v1/user/profile/photo`

**Controller:** `ProfileController@uploadPhoto`  
**Request:** `Profile\UploadPhotoRequest`  
**Middleware:** `large.files`, `bypass.post.size`

**Validation:**
```php
'profile_photo' => [
    'required',
    'image',
    'mimes:' . config('upload.allowed_image_types'),
    'max:' . config('upload.max_file_size'),
    'dimensions:min_width=' . $dimensions['min_width'] . 
               ',min_height=' . $dimensions['min_height'] .
               ',max_width=' . $dimensions['max_width'] . 
               ',max_height=' . $dimensions['max_height']
]
```

**Configuration (from config/upload.php):**
- Max size: 5.1 MB (5222 KB)
- Formats: JPEG, PNG, JPG, WEBP
- Dimensions:
  - Min: 100x100 px
  - Max: 2000x2000 px
- Storage: `storage/app/public/profile-photos/`

**Better Implementation:**
- Uses configuration file for flexibility
- Has dimension validation
- Separate storage directory

---

### 4. Bank Account Photo
**Request:** `StoreBankAccountRequest`, `UpdateBankAccountRequest`

**Validation:**
```php
'photo_rek' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048'
```

**Configuration:**
- Max size: 2 MB
- Formats: JPEG, PNG, JPG, WEBP
- Optional field

---

### 5. Theme Preview Image
**Request:** `StoreJenisThemaRequest`

**Validation:**
```php
'preview_image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048'
```

**Configuration:**
- Max size: 2 MB
- Formats: JPEG, JPG, PNG, WEBP
- Optional field

---

### 6. Video/Website Category Images
**Controllers:**
- `Api\Admin\VideoInvitationCategoryController`
- `Api\Admin\WebsiteInvitationCategoryController`

**Storage:**
- `storage/app/public/video-categories/`
- `storage/app/public/website-categories/`

**Note:** Currently empty directories (no files found)

---

## Critical Issues

### 1. Inconsistent Validation
Different endpoints use different max sizes:
- Gallery: 5.1 MB
- Profile: 5.1 MB (with dimension check)
- Mempelai: 2 MB
- Others: 2 MB

### 2. No Image Optimization
None of the controllers implement:
- Image compression
- Format conversion (e.g., to WebP)
- Thumbnail generation
- Resolution optimization

### 3. High Storage Usage
Average 2.9 MB per gallery image is excessive for web display.

### 4. No Cleanup Strategy
No automated cleanup for:
- Orphaned files when records are deleted
- Old/replaced images
- Temporary uploads

### 5. Performance Impact
Large images (2.9 MB) will cause:
- Slow page loads
- High bandwidth usage
- Poor mobile performance

---

## Recommendations

### Immediate Actions

1. **Implement Image Compression**
Add intervention/image package:
```bash
composer require intervention/image
```

2. **Standardize Max Sizes**
Recommended limits:
- Profile photos: 2 MB max, compress to 500 KB
- Gallery photos: 5 MB max, compress to 1 MB
- Cover photos: 3 MB max, compress to 800 KB

3. **Add Image Optimization Service**
Create `ImageOptimizationService` to handle:
- Automatic compression
- Format conversion (WebP)
- Thumbnail generation
- Quality adjustment

4. **Standardize Validation**
Use consistent validation across all endpoints:
```php
'photo' => [
    'required',
    'image',
    'mimes:jpeg,jpg,png,webp',
    'max:5120',  // 5 MB
    'dimensions:min_width=100,min_height=100,max_width=4000,max_height=4000'
]
```

5. **Implement File Cleanup**
Add observers to models to delete files when records are deleted.

### Long-term Improvements

1. **Cloud Storage Integration**
Move to S3 or similar for:
- CDN delivery
- Better scalability
- Automatic backups

2. **Image Processing Queue**
Use Laravel queues for:
- Async compression
- Thumbnail generation
- Format conversion

3. **Storage Monitoring**
Implement storage usage tracking:
- Per-user storage limits
- Total storage alerts
- Automatic cleanup policies

4. **API Rate Limiting**
Add specific rate limits for upload endpoints to prevent abuse.

---

## Storage Projections

### Current State
- 7 images = 12 MB
- Average per image = 1.7 MB

### Projected Usage (1000 users)
- Without optimization: 1.7 MB × 5 photos × 1000 = 8.5 GB
- With optimization (500 KB avg): 500 KB × 5 photos × 1000 = 2.5 GB
- Savings: 6 GB (70% reduction)

### Cost Impact
Assuming $0.023/GB/month (AWS S3):
- Without optimization: $0.195/month
- With optimization: $0.058/month
- Annual savings: $1.64 (scales with user growth)

---

## Action Plan Priority

### High Priority
1. Add image compression to GaleryController (highest storage usage)
2. Implement file cleanup on deletion
3. Standardize validation rules

### Medium Priority
4. Create ImageOptimizationService
5. Add thumbnail generation
6. Implement storage monitoring

### Low Priority
7. Cloud storage migration
8. Queue-based processing
9. Advanced optimization (WebP conversion)

---

## Configuration Changes Needed

### Update config/upload.php
```php
return [
    'max_file_size' => 5120, // 5 MB in KB
    'max_file_size_mb' => '5',
    
    'allowed_image_types' => ['jpeg', 'jpg', 'png', 'webp'],
    
    'image_dimensions' => [
        'min_width' => 100,
        'min_height' => 100,
        'max_width' => 4000,
        'max_height' => 4000,
    ],
    
    'compression' => [
        'enabled' => true,
        'quality' => 85,
        'max_width' => 2000,
        'max_height' => 2000,
    ],
    
    'thumbnail' => [
        'enabled' => true,
        'width' => 300,
        'height' => 300,
    ],
];
```

---

**Analysis Date:** October 21, 2025  
**Current Total Storage:** 21.22 MB  
**Total Image Files:** 7  
**Status:** Requires optimization
