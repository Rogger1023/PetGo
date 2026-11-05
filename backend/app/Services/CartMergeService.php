<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartMergeService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly GuestCartService $guestCartService
    ) {
    }

    public function merge(User $user, string $guestToken): Cart
    {
        return DB::transaction(function () use ($user, $guestToken) {

            // 1. Localiza e bloqueia o carrinho do usuário.
            $userCart = $this->cartService->getCart($user);

            // 2. Identifica o carrinho visitante pelo token.
            $guestCart = $this->guestCartService
                ->findByToken($guestToken);

            // 3. Bloqueia o carrinho visitante.
            $guestCart = Cart::query()
                ->whereKey($guestCart->id)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            // 4. Carrega os itens em ordem previsível.
            $guestItems = $guestCart->items()
                ->orderBy('product_id')
                ->get();

            // 5. Valida todos os itens antes de alterar qualquer um.
            foreach ($guestItems as $guestItem) {
                $product = Product::query()
                    ->lockForUpdate()
                    ->findOrFail($guestItem->product_id);

                if (!$product->is_active) {
                    throw ValidationException::withMessages([
                        'cart' => "Produto {$product->id} indisponível.",
                    ]);
                }

                $existingItem = $userCart->items()
                    ->where('product_id', $product->id)
                    ->first();

                $totalQuantity = $guestItem->quantity
                    + ($existingItem?->quantity ?? 0);

                if ($totalQuantity > $product->stock) {
                    throw ValidationException::withMessages([
                        'cart' => "Estoque insuficiente para o produto {$product->id}.",
                    ]);
                }
            }

            // 6. Transfere as quantidades para o carrinho autenticado.
            foreach ($guestItems as $guestItem) {
                $existingItem = $userCart->items()
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existingItem) {
                    $existingItem->update([
                        'quantity' => $existingItem->quantity
                            + $guestItem->quantity,
                    ]);
                } else {
                    $userCart->items()->create([
                        'product_id' => $guestItem->product_id,
                        'quantity' => $guestItem->quantity,
                    ]);
                }
            }

            // 7. Remove os itens transferidos do carrinho visitante.
            $guestCart->items()->delete();

            // 8. Invalida o carrinho e seu token.
            $guestCart->status = 'merged';
            $guestCart->guest_token_hash = null;
            $guestCart->save();

            return $userCart->fresh('items.product.category');
        });
    }
}
