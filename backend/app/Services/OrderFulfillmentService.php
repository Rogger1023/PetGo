<?php

namespace App\Services;

use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderFulfillmentService
{
    public function advance(
    int $orderId,
    string $targetStatus,
    ?User $actor = null
    ): Order {

        if (!in_array($targetStatus, ['shipped', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status de destino inválido.',
            ]);
        }

        return DB::transaction(function () use (
            $orderId,
            $targetStatus,
            $actor
        ) {
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = $order->payment()
                ->lockForUpdate()
                ->first();

            if (
                !$payment ||
                $payment->status !== 'paid' ||
                $payment->paid_at === null
            ) {
                throw new ConflictHttpException(
                    'O pedido não possui pagamento confirmado.'
                );
            }

            if ($order->status === $targetStatus) {
                return $order->load([
                    'user',
                    'items',
                    'payment',
                ]);
            }

            $allowedTransitions = [
                'processing' => 'shipped',
                'shipped' => 'delivered',
            ];

            $nextStatus = $allowedTransitions[$order->status] ?? null;

            if ($nextStatus !== $targetStatus) {
                throw new ConflictHttpException(
                    'Transição de status não permitida.'
                );
            }

            $previousStatus = $order->status;

            $order->status = $targetStatus;
            $order->save();

            app(OrderStatusHistoryService::class)->record(
                order: $order,
                fromStatus: $previousStatus,
                toStatus: $order->status,
                source: 'fulfillment',
                actor: $actor
            );

            return $order->fresh([
                'user',
                'items',
                'payment',
            ]);

        }, 3);
    }
}
