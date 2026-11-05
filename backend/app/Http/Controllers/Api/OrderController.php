<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()
            ->orders()
            ->with(['items', 'payment'])
            ->orderByDesc('id')
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    public function show(Request $request, int $order): OrderResource
    {
        $order = $request->user()
            ->orders()
            ->with(['items', 'payment'])
            ->whereKey($order)
            ->firstOrFail();

        return new OrderResource($order);
    }
}
