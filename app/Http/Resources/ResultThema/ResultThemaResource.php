<?php

namespace App\Http\Resources\ResultThema;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Themas\ThemaResource;
use App\Http\Resources\JenisThemas\JenisThemasResource;
use App\Http\Resources\User\UserResource;

class ResultThemaResource extends JsonResource
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
            'user' => new UserResource($this->user),
            'thema' => new ThemaResource($this->thema),
            'jenis thema' => new JenisThemasResource($this->jenisThema)
        ];
    }
}
