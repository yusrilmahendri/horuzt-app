<?php

namespace App\Http\Resources\JenisThemas;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CategoryThemas\CategoryResource;

class JenisThemasResource extends JsonResource
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
            'category' => new CategoryResource($this->jenisThemas),  
            'name' => $this->name,
            'price' => $this->price,
            'preview' => $this->preview,
            'url_thema' => $this->url_thema,
        ];
    }
}
