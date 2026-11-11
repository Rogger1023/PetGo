<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderStatusHistoryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        int $order
    ): AnonymousResourceCollection {

        $customerOrder = $request->user()
            ->orders()
            ->whereKey($order)
            ->firstOrFail();

        $histories = $customerOrder
            ->statusHistories()
            ->orderBy('id')
            ->get();

        return OrderStatusHistoryResource::collection(
            $histories
        );
    }
}
