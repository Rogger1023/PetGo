<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;

class OrderStatusHistoryService
{
    public function record(
        Order $order,
        ?string $fromStatus,
        string $toStatus,
        string $source,
        ?User $actor = null
    ): OrderStatusHistory {
        $history = new OrderStatusHistory();

        $history->order()->associate($order);

        if ($actor !== null) {
            $history->actor()->associate($actor);
        }

        $history->from_status = $fromStatus;
        $history->to_status = $toStatus;
        $history->source = $source;
        $history->created_at = now();

        $history->save();

        return $history;
    }
}
