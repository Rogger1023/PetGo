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
            // 1. Serializa os checkouts deste usuário.
            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Impede pedidos duplicados.
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

            // 3. Confirma que o endereço pertence ao usuário.
            $address = $user->addresses()
                ->whereKey($addressId)
                ->firstOrFail();

            // 4. Localiza e bloqueia o carrinho ativo.
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

            // 5. Confere os produtos e calcula os valores.
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

            // Frete provisoriamente gratuito nesta etapa.
            $shipping = 0;
            $total = $subtotal + $shipping;

            // 6. Cria o pedido.
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

            // 7. Registra os itens e reserva o estoque.
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

                $product->stock -= $line['quantity'];
                $product->save();
            }

            // 8. Registra um pagamento pendente.
            $payment = new Payment();
            $payment->order()->associate($order);
            $payment->status = 'pending';
            $payment->save();

            // 9. Finaliza o carrinho utilizado.
            $cart->status = 'checked_out';
            $cart->save();

            return $order->load('items', 'payment');
        }, 3);
    }
}
