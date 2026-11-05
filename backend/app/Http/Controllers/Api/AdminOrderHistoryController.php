<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderStatusHistoryResource;
use App\Models\Order;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderHistoryController extends Controller
{
    public function __invoke(
        int $order
    ): AnonymousResourceCollection {

        // Administrador pode consultar qualquer pedido.
        $orderModel = Order::query()
            ->whereKey($order)
            ->firstOrFail();

        // Carrega os eventos e seus responsáveis.
        $histories = $orderModel
            ->statusHistories()
            ->with('actor')
            ->orderBy('id')
            ->get();

        return OrderStatusHistoryResource::collection(
            $histories
        );
    }
}
