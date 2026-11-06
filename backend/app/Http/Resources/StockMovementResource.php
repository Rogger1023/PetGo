<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'actor_user_id' => $this->actor_user_id,
            'order_id' => $this->order_id,
            'source' => $this->source,
            'quantity_delta' => $this->quantity_delta,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
