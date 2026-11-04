<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    public function index(): AnonymousResourceCollection
    {
        $products = $this->productService->listActiveProducts();

        return ProductResource::collection($products);
    }

    public function show(Product $product): ProductResource
    {
        $product = $this->productService->findActiveProduct($product);

        return new ProductResource($product);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        $product = $this->productService->createProduct($data);

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): ProductResource {
        $data = $request->validated();

        $product = $this->productService->updateProduct(
            $product,
            $data
        );

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $this->productService->deactivateProduct($product);

        return response()->noContent();
    }
}
