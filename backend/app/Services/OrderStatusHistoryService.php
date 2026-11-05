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
        // 1. Instancia um novo evento de histórico.
        $history = new OrderStatusHistory();

        // 2. Associa o evento ao pedido.
        $history->order()->associate($order);

        // 3. Associa o usuário responsável, quando houver.
        if ($actor !== null) {
            $history->actor()->associate($actor);
        }

        // 4. Define os dados da mudança de status.
        $history->from_status = $fromStatus;
        $history->to_status = $toStatus;
        $history->source = $source;
        $history->created_at = now();

        // 5. Salva o registro no banco.
        $history->save();

        return $history;
    }
}
