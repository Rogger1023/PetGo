<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    
    public function getCart(User $user): Cart
    {
        return DB::transaction(function () use ($user) {
            User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $cart = $user->carts()
                ->where('status', 'active')
                ->first();

            if (!$cart) {
                $cart = new Cart();
                $cart->user()->associate($user);
                $cart->status = 'active';
                $cart->save();
            }

            return $cart->load('items.product.category');
        });
    }
    
    public function addItem(
        User $user,
        int $productId,
        int $quantity
    ): Cart {
        return DB::transaction(function () use (
            $user,
            $productId,
            $quantity
        ) {
            // 1. Obtém o carrinho e bloqueia o usuário
            $cart = $this->getCart($user);

            // 2. Bloqueia o produto para verificar seu estoque
            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($productId);

            $this->validateProduct($product);

            // 3. Procura o produto dentro do carrinho
            $item = $cart->items()
                ->where('product_id', $product->id)
                ->first();

            // 4. Calcula a nova quantidade
            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            $this->validateStock($product, $newQuantity);

            // 5. Atualiza ou cria o item
            if ($item) {
                $item->update([
                    'quantity' => $newQuantity,
                ]);
            } else {
                $cart->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);
            }

            // 6. Retorna o carrinho atualizado
            return $cart->fresh('items.product.category');
        });
    }
 
    public function updateItem(
        User $user,
        int $itemId,
        int $quantity
    ): Cart {
        return DB::transaction(function () use (
            $user,
            $itemId,
            $quantity
        ) {
            $cart = $this->getCart($user);

            $item = $cart->items()
                ->whereKey($itemId)
                ->firstOrFail();

            $product = Product::query()
                ->lockForUpdate()
                ->findOrFail($item->product_id);

            $this->validateProduct($product);
            $this->validateStock($product, $quantity);

            $item->update([
                'quantity' => $quantity,
            ]);

            return $cart->fresh('items.product.category');
        });
    }

    public function removeItem(
        User $user,
        int $itemId
    ): Cart {
        return DB::transaction(function () use (
            $user,
            $itemId
        ) {
            $cart = $this->getCart($user);

            $item = $cart->items()
                ->whereKey($itemId)
                ->firstOrFail();

            $item->delete();

            return $cart->fresh('items.product.category');
        });
    }

    private function validateProduct(Product $product): void
    {
        if (!$product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Este produto está indisponível.',
            ]);
        }
    }

    private function validateStock(
        Product $product,
        int $quantity
    ): void {
        if ($quantity > $product->stock) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantidade superior ao estoque disponível.',
            ]);
        }
    }
}
