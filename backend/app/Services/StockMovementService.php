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
        // O estoque nunca pode ser negativo.
        if ($stockBefore < 0 || $stockAfter < 0) {
            throw new InvalidArgumentException(
                'O estoque não pode ser negativo.'
            );
        }

        // Calcula a diferença entre o estoque atual e anterior.
        $quantityDelta = $stockAfter - $stockBefore;

        // Não registra uma movimentação sem alteração.
        if ($quantityDelta === 0) {
            throw new InvalidArgumentException(
                'A movimentação deve alterar o estoque.'
            );
        }

        $movement = new StockMovement();

        // Associa o produto à movimentação.
        $movement->product()->associate($product);

        // Registra o responsável, quando disponível.
        if ($actor !== null) {
            $movement->actor()->associate($actor);
        }

        // Associa um pedido, quando existir.
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
