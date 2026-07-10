<?php

namespace App\Http\Resources\Photo;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $path = $this->file_path ?: $this->photo;

        return [
            'id' => $this->id,
            'photo_type' => $this->photo_type ?: 'gallery',
            'photo_url' => $path ? Storage::disk('public')->url($path) : null,
            'description' => $this->description,
            'position' => $this->position ?: 'center',
            'display_mode' => $this->display_mode ?: 'cover',
            'focal_point_x' => $this->focal_point_x,
            'focal_point_y' => $this->focal_point_y,
            'object_position' => $this->object_position,
            'is_featured' => (bool) ($this->is_featured ?? false),
            'sort_order' => (int) ($this->sort_order ?? 0),
            'original_size' => $this->original_size,
            'compressed_size' => $this->compressed_size,
            'quality' => (int) ($this->quality ?? 85),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
