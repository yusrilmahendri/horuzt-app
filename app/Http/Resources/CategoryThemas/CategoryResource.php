<?php

namespace App\Http\Resources\CategoryThemas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thema_video' => $this->thema_video,
            'slug_video' => $this->slug_video,
            'thema_website' => $this->thema_website,
            'slug_website' => $this->slug_website,
        ];
    }
}
