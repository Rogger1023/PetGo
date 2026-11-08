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
            'image_url' => $this->image_path
                ? "/api/products/{$this->id}/image"
                : null,
            'price_cents' => $this->price_cents,
            'stock' => (int) $this->stock,
            'in_stock' => $this->stock > 0,
            'category' => new CategoryResource(
                $this->whenLoaded('category')
            ),
            'category_id' => $this->category_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
