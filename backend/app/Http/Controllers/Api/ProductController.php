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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $productService
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $search = trim($filters['search'] ?? '');
        $categoryId = $filters['category_id'] ?? null;

        $products = Product::query()
        ->with('category')
        ->where('is_active', true)

        ->when($search !== '', function ($query) use ($search) {
            $query->where('name', 'like', '%' . $search . '%');
        })

        ->when($categoryId !== null, function ($query) use ($categoryId) {
            $query->where('category_id', $categoryId);
        })

        ->orderBy('name')
        ->paginate(12);

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

        $product = $this->productService->createProduct(
            $data,
            $request->user()
        );

        return (new ProductResource($product))
            ->response()
            ->setStatusCode(201);
    }
    
    public function image(Product $product): StreamedResponse
    {
        abort_unless(
            $product->is_active && $product->image_path,
            404
        );

        $disk = Storage::disk('public');

        abort_unless(
            $disk->exists($product->image_path),
            404
        );

        return $disk->response($product->image_path);
    }

    public function update(
        UpdateProductRequest $request,
        Product $product
    ): ProductResource {
        $data = $request->validated();

        $product = $this->productService->updateProduct(
            $product,
            $data,
            $request->user()
        );

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $this->productService->deactivateProduct($product);

        return response()->noContent();
    }
}
