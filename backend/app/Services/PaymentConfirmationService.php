<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaymentConfirmationService
{
    public function confirm(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {

            // 1. Bloqueia o pedido durante a confirmação.
            $order = Order::query()
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Bloqueia o pagamento associado.
            $payment = $order->payment()
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new ConflictHttpException(
                    'Pagamento não encontrado para este pedido.'
                );
            }

            // 3. Confirmação repetida não altera os dados novamente.
            if (
                $order->status === 'processing' &&
                $payment->status === 'paid' &&
                $payment->paid_at !== null
            ) {
                return $order->load(['user', 'items', 'payment']);
            }

            // 4. Somente pedidos pendentes podem ser confirmados.
            if (
                $order->status !== 'pending_payment' ||
                $payment->status !== 'pending' ||
                $payment->paid_at !== null
            ) {
                throw new ConflictHttpException(
                    'Este pedido não permite confirmação de pagamento.'
                );
            }

            // 5. Atualiza o pagamento.
            $payment->status = 'paid';
            $payment->paid_at = now();
            $payment->save();

            // 6. Atualiza o pedido.
            $order->status = 'processing';
            $order->save();

            // 7. Retorna o pedido com seus relacionamentos.
            return $order->fresh([
                'user',
                'items',
                'payment',
            ]);

        }, 3);
    }
}
