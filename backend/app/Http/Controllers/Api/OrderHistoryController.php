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

        // Busca somente pedidos do cliente autenticado.
        $customerOrder = $request->user()
            ->orders()
            ->whereKey($order)
            ->firstOrFail();

        // Recupera os eventos na ordem em que ocorreram.
        $histories = $customerOrder
            ->statusHistories()
            ->orderBy('id')
            ->get();

        // Converte os eventos em resposta JSON.
        return OrderStatusHistoryResource::collection(
            $histories
        );
    }
}
