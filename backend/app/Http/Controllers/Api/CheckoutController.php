<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = $this->checkoutService->checkout(
            $request->user(),
            (int) $data['address_id'],
            $data['checkout_key']
        );

        return response()->json([
            'message' => 'Pedido processado com sucesso.',
            'order' => new OrderResource($order),
        ], 201);
    }
}
