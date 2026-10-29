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
}
