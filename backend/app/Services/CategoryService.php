<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CategoryService
{
    public function listCategories(): LengthAwarePaginator
    {
        return Category::query()
            ->orderBy('name')
            ->paginate(12);
    }

    public function findCategory(Category $category): Category
    {
        return $category;
    }

    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    public function updateCategory(
        Category $category,
        array $data
    ): Category {
        $category->update($data);

        return $category->refresh();
    }

    public function deleteCategory(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new ConflictHttpException(
                'Não é possível excluir uma categoria que possui produtos.'
            );
        }

        $category->delete();
    }
}
