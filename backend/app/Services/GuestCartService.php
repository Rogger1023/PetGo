<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestCartService
{
    public function create(): array
    {
        $token = Str::random(64);

        $cart = new Cart();
        $cart->guest_token_hash = hash('sha256', $token);
        $cart->status = 'active';
        $cart->save();

        return [
            'cart' => $cart->load('items.product.category'),
            'token' => $token,
        ];
    }

    public function findByToken(string $token): Cart
    {
        if (!preg_match('/\A[a-zA-Z0-9]{64}\z/D', $token)) {
            throw ValidationException::withMessages([
                'guest_cart_token' => 'Token do carrinho inválido.',
            ]);
        }

        $cart = Cart::query()
            ->where('guest_token_hash', hash('sha256', $token))
            ->whereNull('user_id')
            ->where('status', 'active')
            ->firstOrFail();

        return $cart->load('items.product.category');
    }
}
