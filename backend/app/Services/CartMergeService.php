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
 
            $userCart = $this->cartService->getCart($user);
    
            $guestCart = $this->guestCartService
                ->findByToken($guestToken);
         
            $guestCart = Cart::query()
                ->whereKey($guestCart->id)
                ->whereNull('user_id')
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $guestItems = $guestCart->items()
                ->orderBy('product_id')
                ->get();

            $maxQuantity = (int) config(
                'cart.max_quantity_per_item',
                20
            );
    
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

                    if (
                    $guestItem->quantity < 1 ||
                    $totalQuantity < 1 ||
                    $totalQuantity > $maxQuantity
                ) {
                    throw ValidationException::withMessages([
                        'cart' =>
                            "O produto {$product->id} deve respeitar o limite de {$maxQuantity} unidades por carrinho.",
                    ]);
                }

                if ($totalQuantity > $product->stock) {
                    throw ValidationException::withMessages([
                        'cart' => "Estoque insuficiente para o produto {$product->id}.",
                    ]);
                }
            }

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

            $guestCart->items()->delete();
            $guestCart->status = 'merged';
            $guestCart->guest_token_hash = null;
            $guestCart->save();

            return $userCart->fresh('items.product.category');
        });
    }
}
