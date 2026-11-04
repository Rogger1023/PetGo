<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\CartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService
    ) {
    }

    
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart(
            $request->user()
        );

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(200);
    }

    public function store(
        CartItemRequest $request
    ): CartResource {
        $data = $request->validated();

        $cart = $this->cartService->addItem(
            $request->user(),
            (int) $data['product_id'],
            (int) $data['quantity']
        );

        return new CartResource($cart);
    }

    public function update(
        Request $request,
        int $item
    ): CartResource {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->updateItem(
            $request->user(),
            $item,
            (int) $data['quantity']
        );

        return new CartResource($cart);
    }

    public function destroy(
        Request $request,
        int $item
    ): CartResource {
        $cart = $this->cartService->removeItem(
            $request->user(),
            $item
        );

        return new CartResource($cart);
    }
}
