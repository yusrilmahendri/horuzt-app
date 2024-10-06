<?php

namespace App\Http\Resources\ResultPernikahan;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Pernikahan\PernikahanResource;

class ResultPernikahanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pernikahan' => new PernikahanResource($this->whenLoaded('pernikahan')),
            'mempelai' => $this->whenLoaded('mempelai'),
            'acara' => $this->whenLoaded('acara'),
            'pengunjung' => $this->whenLoaded('pengunjung'),
            'qoute' => $this->whenLoaded('qoute'),
        ];
    }
}
