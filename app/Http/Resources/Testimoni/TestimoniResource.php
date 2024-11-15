<?php

namespace App\Http\Resources\Testimoni;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User\UserResource;

class TestimoniResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->user),
            'provinsi' => $this->provinsi,
            'ulasan' => $this->ulasan,
            'status' => $this->status,
        ];
    }
}
