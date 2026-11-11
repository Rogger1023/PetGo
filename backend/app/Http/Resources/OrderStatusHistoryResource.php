<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderStatusHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];

        if ($request->routeIs('admin.orders.history')) {

            $data['source'] = $this->source;

            $data['actor'] = $this->actor
                ? [
                    'id' => $this->actor->id,
                    'name' => $this->actor->name,
                ]
                : null;
        }

        return $data;
    }
}
