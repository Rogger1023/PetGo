<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderStatusUpdateRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderFulfillmentService;

class OrderFulfillmentController extends Controller
{
    public function __construct(
        private readonly OrderFulfillmentService $fulfillmentService
    ) {
    }

    public function __invoke(
        OrderStatusUpdateRequest $request,
        int $order
    ): OrderResource {
        $data = $request->validated();

        $updatedOrder = $this->fulfillmentService->advance(
            $order,
            $data['status']
        );

        return new OrderResource($updatedOrder);
    }
}
