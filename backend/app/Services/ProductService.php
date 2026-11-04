<?php

namespace App\Services;

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

    public function createProduct(array $data): Product
    {
        $product = Product::create($data);

        return $product->load('category');
    }

    public function updateProduct(
        Product $product,
        array $data
    ): Product {
        $product->update($data);

        return $product->load('category');
    }

    public function deactivateProduct(Product $product): void
    {
        $product->update([
            'is_active' => false,
        ]);
    }
}
