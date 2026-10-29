<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'shipping_cents' => $this->shipping_cents,
            'subtotal_cents' => $this->subtotal_cents,
            'total_cents' => $this->total_cents,
            'address_snapshot' => $this->address_snapshot,
            'items' => OrderItemResource::collection(
                $this->whenLoaded('items')
            ),
            'created_at' => $this->created_at,
        ];
    }
}
