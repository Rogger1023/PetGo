<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A rota de gestão também deverá exigir autenticação e autorização.
        return true;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product
            ? $product->getKey()
            : $product;

        $slugRule = Rule::unique('products', 'slug');

        if ($productId !== null) {
            $slugRule->ignore($productId);
        }

        return [
            'category_id' => [
                'sometimes', 'required', 'integer', 'exists:categories,id',
            ],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:220',
                'alpha_dash',
                $slugRule,
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'image_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'price_cents' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
