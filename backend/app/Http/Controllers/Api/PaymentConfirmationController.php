<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\PaymentConfirmationService;

class PaymentConfirmationController extends Controller
{
    public function __construct(
        private readonly PaymentConfirmationService $paymentConfirmationService
    ) {
    }

    public function __invoke(int $order): OrderResource
    {
        $confirmedOrder = $this->paymentConfirmationService->confirm(
            $order
        );

        return new OrderResource($confirmedOrder);
    }
}
