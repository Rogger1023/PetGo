<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->with(['user', 'items', 'payment'])
            ->orderByDesc('id')
            ->paginate(15);

        return OrderResource::collection($orders);
    }

    public function show(int $order): OrderResource
    {
        $order = Order::query()
            ->with(['user', 'items', 'payment'])
            ->findOrFail($order);

        return new OrderResource($order);
    }
}
