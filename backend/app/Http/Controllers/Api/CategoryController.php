<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categoryService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $categories = $this->categoryService->listCategories();

        return CategoryResource::collection($categories);
    }

    public function show(Category $category): CategoryResource
    {
        $category = $this->categoryService->findCategory($category);

        return new CategoryResource($category);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = $this->categoryService->createCategory($data);

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCategoryRequest $request,
        Category $category
    ): CategoryResource {
        $data = $request->validated();

        $category = $this->categoryService->updateCategory(
            $category,
            $data
        );

        return new CategoryResource($category);
    }

    public function destroy(Category $category): Response
    {
        $this->categoryService->deleteCategory($category);

        return response()->noContent();
    }
}
