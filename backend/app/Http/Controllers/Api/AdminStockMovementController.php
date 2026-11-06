<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminStockMovementController extends Controller
{
    public function __invoke(
        Product $product
    ): AnonymousResourceCollection {
        $movements = $product->stockMovements()
            ->orderByDesc('id')
            ->paginate(20);

        return StockMovementResource::collection($movements);
    }
}
