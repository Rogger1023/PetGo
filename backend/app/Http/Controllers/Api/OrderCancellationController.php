<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\OrderCancellationService;
use Illuminate\Http\Request;

class OrderCancellationController extends Controller
{
    public function __construct(
        private readonly OrderCancellationService $cancellationService
    ) {
    }

    public function __invoke(
        Request $request,
        int $order
    ): OrderResource {
        $cancelledOrder = $this->cancellationService->cancel(
            $request->user(),
            $order
        );

        return new OrderResource($cancelledOrder);
    }
}
