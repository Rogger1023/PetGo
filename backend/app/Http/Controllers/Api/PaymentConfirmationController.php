<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\PaymentConfirmationService;
use Illuminate\Http\Request;

class PaymentConfirmationController extends Controller
{
    public function __construct(
        private readonly PaymentConfirmationService $paymentConfirmationService
    ) {
    }

   
    public function __invoke(
        Request $request,
        int $order
    ): OrderResource {
        $confirmedOrder = $this->paymentConfirmationService->confirm(
            $order,
            $request->user()
        );

        return new OrderResource($confirmedOrder);
    }
    }
