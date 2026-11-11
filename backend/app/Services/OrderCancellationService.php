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

            $order = Order::query()
                ->where('user_id', $user->id)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'cancelled') {
                return $order->load(['items', 'payment']);
            }

            if ($order->status !== 'pending_payment') {
                throw new ConflictHttpException(
                    'Este pedido não pode ser cancelado.'
                );
            }

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

            $items = $order->items()
                ->orderBy('product_id')
                ->get();

            foreach ($items as $item) {

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

            $previousStatus = $order->status;

            $payment->status = 'cancelled';
            $payment->save();

            $order->status = 'cancelled';
            $order->save();

            app(OrderStatusHistoryService::class)->record(
                order: $order,
                fromStatus: $previousStatus,
                toStatus: $order->status,
                source: 'order_cancellation',
                actor: $user
            );

            return $order->fresh(['items', 'payment']);


        }, 3);
    }
}
