<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'price_cents' => $this->price_cents,
            'in_stock' => $this->stock > 0,
            'category' => new CategoryResource(
                $this->whenLoaded('category')
            ),
        ];
    }
}
