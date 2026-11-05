<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderFulfillmentService
{
    public function advance(
        int $orderId,
        string $targetStatus
    ): Order {

        // 1. Aceita apenas estados de logística.
        if (!in_array($targetStatus, ['shipped', 'delivered'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Status de destino inválido.',
            ]);
        }

        return DB::transaction(function () use (
            $orderId,
            $targetStatus
        ) {

            // 2. Bloqueia o pedido durante a atualização.
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Bloqueia e verifica o pagamento.
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

            // 4. Repetir a mesma atualização não altera o pedido.
            if ($order->status === $targetStatus) {
                return $order->load([
                    'user',
                    'items',
                    'payment',
                ]);
            }

            // 5. Define as transições permitidas.
            $allowedTransitions = [
                'processing' => 'shipped',
                'shipped' => 'delivered',
            ];

            $nextStatus = $allowedTransitions[$order->status] ?? null;

            // 6. Bloqueia transições inválidas.
            if ($nextStatus !== $targetStatus) {
                throw new ConflictHttpException(
                    'Transição de status não permitida.'
                );
            }

            // 7. Atualiza o estado do pedido.
            $order->status = $targetStatus;
            $order->save();

            // 8. Retorna o pedido atualizado.
            return $order->fresh([
                'user',
                'items',
                'payment',
            ]);

        }, 3);
    }
}
