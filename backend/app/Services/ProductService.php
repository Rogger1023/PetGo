<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function listActiveProducts(): LengthAwarePaginator
    {
        return Product::query()
            ->where('is_active', true)
            ->with('category')
            ->orderBy('name')
            ->paginate(12);
    }

    public function findActiveProduct(Product $product): Product
    {
        if (!$product->is_active) {
            abort(404, 'Produto não encontrado.');
        }

        return $product->load('category');
    }

    public function createProduct(
        array $data,
        ?User $actor = null
    ): Product {
        return DB::transaction(function () use ($data, $actor) {

            // Cria o produto normalmente.
            $product = Product::create($data);

            // Registra a entrada inicial quando houver estoque.
            if ($product->stock > 0) {
                app(StockMovementService::class)->record(
                    product: $product,
                    stockBefore: 0,
                    stockAfter: (int) $product->stock,
                    source: 'product_creation',
                    actor: $actor,
                    reason: 'Estoque inicial do produto.'
                );
            }

            return $product->load('category');

        }, 3);
    }

    public function updateProduct(
        Product $product,
        array $data,
        ?User $actor = null
    ): Product {
        return DB::transaction(function () use (
            $product,
            $data,
            $actor
        ) {

            // Recupera o produto com bloqueio de escrita.
            $lockedProduct = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Guarda a quantidade anterior.
            $stockBefore = (int) $lockedProduct->stock;

            // Preenche os campos recebidos.
            $lockedProduct->fill($data);

            // Obtém a nova quantidade.
            $stockAfter = (int) $lockedProduct->stock;

            // Salva as alterações.
            $lockedProduct->save();

            // Registra apenas quando o estoque foi alterado.
            if ($stockBefore !== $stockAfter) {
                app(StockMovementService::class)->record(
                    product: $lockedProduct,
                    stockBefore: $stockBefore,
                    stockAfter: $stockAfter,
                    source: 'admin_product_update',
                    actor: $actor,
                    reason: 'Ajuste administrativo de estoque.'
                );
            }

            return $lockedProduct->load('category');

        }, 3);
    }


    public function deactivateProduct(Product $product): void
    {
        $product->update([
            'is_active' => false,
        ]);
    }
}
