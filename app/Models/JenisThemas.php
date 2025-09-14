<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\CategoryThemas;

class JenisThemas extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'image',
        'price',
        'preview',
        'preview_image',
        'thumbnail_image',
        'url_thema',
        'is_active',
        'description',
        'demo_url',
        'sort_order',
        'features'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'features' => 'array',
        'price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(CategoryThemas::class, 'category_id');
    }

    public function themas()
    {
        return $this->belongsToMany(Themas::class, 'result_themas', 'jenis_id', 'thema_id');
    }

    public function resultThemas()
    {
        return $this->hasMany(ResultThemas::class, 'jenis_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    public function scopeWithActiveCategory($query)
    {
        return $query->whereHas('category', function($q) {
            $q->where('is_active', true);
        });
    }

    // Accessors for image URLs
    public function getPreviewImageAttribute($value)
    {
        // Priority: preview_image -> image -> null
        $imageValue = $value ?: ($this->attributes['image'] ?? null);

        if (!$imageValue) {
            return null;
        }

        // If it's already a full URL, return as is
        if (str_starts_with($imageValue, 'http')) {
            return $imageValue;
        }

        // Convert relative path to full URL
        return url('storage/' . $imageValue);
    }

    public function getThumbnailImageAttribute($value)
    {
        // Priority: thumbnail_image -> image -> null
        $imageValue = $value ?: ($this->attributes['image'] ?? null);

        if (!$imageValue) {
            return null;
        }

        // If it's already a full URL, return as is
        if (str_starts_with($imageValue, 'http')) {
            return $imageValue;
        }

        // Convert relative path to full URL
        return url('storage/' . $imageValue);
    }

    public function getPreviewAttribute($value)
    {
        // Priority: preview -> image -> null
        $imageValue = $value ?: ($this->attributes['image'] ?? null);

        if (!$imageValue) {
            return null;
        }

        // If it's already a full URL, return as is
        if (str_starts_with($imageValue, 'http')) {
            return $imageValue;
        }

        // Convert relative path to full URL
        return url('storage/' . $imageValue);
    }
}
