<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use InvalidArgumentException;

class StockMovementService
{
    public function record(
        Product $product,
        int $stockBefore,
        int $stockAfter,
        string $source,
        ?User $actor = null,
        ?Order $order = null,
        ?string $reason = null
    ): StockMovement {
        if ($stockBefore < 0 || $stockAfter < 0) {
            throw new InvalidArgumentException(
                'O estoque não pode ser negativo.'
            );
        }

        $quantityDelta = $stockAfter - $stockBefore;

        if ($quantityDelta === 0) {
            throw new InvalidArgumentException(
                'A movimentação deve alterar o estoque.'
            );
        }

        $movement = new StockMovement();

        $movement->product()->associate($product);

        if ($actor !== null) {
            $movement->actor()->associate($actor);
        }

        if ($order !== null) {
            $movement->order()->associate($order);
        }

        $movement->source = $source;
        $movement->quantity_delta = $quantityDelta;
        $movement->stock_before = $stockBefore;
        $movement->stock_after = $stockAfter;
        $movement->reason = $reason;
        $movement->created_at = now();

        $movement->save();

        return $movement;
    }
}
