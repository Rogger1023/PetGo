<?php

namespace App\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CheckoutService
{
    public function checkout(
        User $user,
        int $addressId,
        string $checkoutKey
    ): Order {
        return DB::transaction(function () use (
            $user,
            $addressId,
            $checkoutKey
        ) {
            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingOrder = Order::query()
                ->where('checkout_key', $checkoutKey)
                ->first();

            if ($existingOrder) {
                if ($existingOrder->user_id !== $user->id) {
                    throw new ConflictHttpException(
                        'Chave de checkout indisponível.'
                    );
                }

                return $existingOrder->load('items', 'payment');
            }

            $address = $user->addresses()
                ->whereKey($addressId)
                ->firstOrFail();

            $cart = $user->carts()
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (!$cart || !$cart->items()->exists()) {
                throw ValidationException::withMessages([
                    'cart' => 'O carrinho está vazio.',
                ]);
            }

            $items = $cart->items()
                ->orderBy('product_id')
                ->get();

            $subtotal = 0;
            $orderLines = [];

            foreach ($items as $item) {
                $product = Product::query()
                    ->whereKey($item->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => "Produto {$product->id} indisponível.",
                    ]);
                }

                $maxQuantity = (int) config(
                    'cart.max_quantity_per_item',
                    20
                );

                if ($item->quantity > $maxQuantity) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            "O produto {$product->id} ultrapassa o limite de {$maxQuantity} unidades por pedido.",
                    ]);
                }

                if (
                    $item->quantity < 1 ||
                    $item->quantity > $product->stock
                ) {
                    throw ValidationException::withMessages([
                        'cart' => "Estoque insuficiente para o produto {$product->id}.",
                    ]);
                }

                $unitPrice = (int) $product->price_cents;
                $lineTotal = $unitPrice * $item->quantity;

                $subtotal += $lineTotal;

                $orderLines[] = [
                    'product' => $product,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $unitPrice,
                    'line_total_cents' => $lineTotal,
                ];
            }

            $shipping = 0;
            $total = $subtotal + $shipping;

            $order = new Order();
            $order->user()->associate($user);
            $order->status = 'pending_payment';
            $order->shipping_cents = $shipping;
            $order->subtotal_cents = $subtotal;
            $order->total_cents = $total;
            $order->checkout_key = $checkoutKey;
            $order->address_snapshot = $address->only([
                'recipient_name',
                'phone',
                'postal_code',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
                'reference',
            ]);
            $order->save();
            app(OrderStatusHistoryService::class)->record(
                order: $order,
                fromStatus: null,
                toStatus: $order->status,
                source: 'checkout',
                actor: $user
            );
            foreach ($orderLines as $line) {
                $product = $line['product'];

                $orderItem = new OrderItem();
                $orderItem->order()->associate($order);
                $orderItem->product()->associate($product);
                $orderItem->product_name = $product->name;
                $orderItem->quantity = $line['quantity'];
                $orderItem->unit_price_cents =
                    $line['unit_price_cents'];
                $orderItem->line_total_cents =
                    $line['line_total_cents'];
                $orderItem->save();
                
                $stockBefore = (int) $product->stock;

                $product->stock -= $line['quantity'];
                $product->save();

                app(StockMovementService::class)->record(
                    product: $product,
                    stockBefore: $stockBefore,
                    stockAfter: (int) $product->stock,
                    source: 'checkout',
                    actor: $user,
                    order: $order
                );

            }

            $payment = new Payment();
            $payment->order()->associate($order);
            $payment->status = 'pending';
            $payment->save();

            $cart->status = 'checked_out';
            $cart->save();

            return $order->load('items', 'payment');
        }, 3);
    }
}
