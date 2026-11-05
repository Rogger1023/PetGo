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
            'user' => $this->whenLoaded(
                'user',
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ]
            ),
            'status' => $this->status,
            'subtotal_cents' => $this->subtotal_cents,
            'shipping_cents' => $this->shipping_cents,
            'total_cents' => $this->total_cents,

            'address' => $this->address_snapshot,

            'items' => $this->whenLoaded(
                'items',
                fn () => $this->items->map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'line_total_cents' => $item->line_total_cents,
                ])
            ),

            'payment' => $this->whenLoaded(
                'payment',
                fn () => [
                    'status' => $this->payment->status,
                    'paid_at' => $this->payment->paid_at,
                ]
            ),

            'created_at' => $this->created_at,
        ];
    }
}
