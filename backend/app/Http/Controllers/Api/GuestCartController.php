<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\CartItemRequest;
use App\Services\CartService;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Services\GuestCartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestCartController extends Controller
{
    public function __construct(
    private readonly GuestCartService $guestCartService,
    private readonly CartService $cartService
    ) {
    }

    public function store(): JsonResponse
    {
        $result = $this->guestCartService->create();

        return response()->json([
            'cart' => new CartResource($result['cart']),
            'guest_cart_token' => $result['token'],
        ], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function show(Request $request): JsonResponse
    {
        $token = $request->header('X-Guest-Cart-Token');

        if (!is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Token do carrinho obrigatório.',
            ], 401);
        }

        $cart = $this->guestCartService->findByToken($token);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store');
    }
    
    public function addItem(CartItemRequest $request): JsonResponse
    {
        $token = $request->header('X-Guest-Cart-Token');

        if (!is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Token do carrinho obrigatório.',
            ], 401);
        }

        $cart = $this->guestCartService->findByToken($token);

        $data = $request->validated();

        $cart = $this->cartService->addItemToCart(
            $cart,
            (int) $data['product_id'],
            (int) $data['quantity']
        );

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store');
    }

    
    public function updateItem(Request $request, int $item): JsonResponse
    {
        $token = $request->header('X-Guest-Cart-Token');

        if (!is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Token do carrinho obrigatório.',
            ], 401);
        }

        $cart = $this->guestCartService->findByToken($token);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->updateItemInCart(
            $cart,
            $item,
            (int) $data['quantity']
        );

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store');
    }

    public function removeItem(Request $request, int $item): JsonResponse
    {
        $token = $request->header('X-Guest-Cart-Token');

        if (!is_string($token) || $token === '') {
            return response()->json([
                'message' => 'Token do carrinho obrigatório.',
            ], 401);
        }

        $cart = $this->guestCartService->findByToken($token);

        $cart = $this->cartService->removeItemFromCart(
            $cart,
            $item
        );

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200)
            ->header('Cache-Control', 'no-store');
    }

}
