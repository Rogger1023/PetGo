<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OrderCancellationService
{
    public function cancel(User $user, int $orderId): Order
    {
        return DB::transaction(function () use ($user, $orderId) {

            // 1. Busca somente pedidos do usuário autenticado.
            // Bloqueia o pedido durante o cancelamento.
            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Cancelamento repetido não devolve estoque novamente.
            if ($order->status === 'cancelled') {
                return $order->load(['items', 'payment']);
            }

            // 3. Apenas pedidos aguardando pagamento podem ser cancelados.
            if ($order->status !== 'pending_payment') {
                throw new ConflictHttpException(
                    'Este pedido não pode ser cancelado.'
                );
            }

            // 4. Bloqueia o pagamento associado ao pedido.
            $payment = $order->payment()
                ->lockForUpdate()
                ->first();

            if (
                !$payment ||
                $payment->status !== 'pending' ||
                $payment->paid_at !== null
            ) {
                throw new ConflictHttpException(
                    'O pagamento não permite o cancelamento.'
                );
            }

            // 5. Obtém os itens em ordem previsível.
            $items = $order->items()
                ->orderBy('product_id')
                ->get();

            // 6. Devolve as quantidades ao estoque.
            foreach ($items as $item) {

                // Um produto removido do catálogo pode ter ID nulo.
                if ($item->product_id === null) {
                    continue;
                }

                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->first();

                if (!$product) {
                    continue;
                }

                $stockBefore = (int) $product->stock;

                $product->stock += (int) $item->quantity;
                $product->save();

                app(StockMovementService::class)->record(
                    product: $product,
                    stockBefore: $stockBefore,
                    stockAfter: (int) $product->stock,
                    source: 'order_cancellation',
                    actor: $user,
                    order: $order
                );

            }

            // Guarda o status anterior antes de modificá-lo.
            $previousStatus = $order->status;

            // Cancela o pagamento pendente.
            $payment->status = 'cancelled';
            $payment->save();

            // Cancela o pedido.
            $order->status = 'cancelled';
            $order->save();

            // NOVO: registra o cancelamento no histórico.
            app(OrderStatusHistoryService::class)->record(
                order: $order,
                fromStatus: $previousStatus,
                toStatus: $order->status,
                source: 'order_cancellation',
                actor: $user
            );

            // Retorna o pedido com seus relacionamentos.
            return $order->fresh(['items', 'payment']);


        }, 3);
    }
}
