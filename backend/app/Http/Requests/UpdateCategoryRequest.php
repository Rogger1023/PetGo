<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');

        $categoryId = $category instanceof Category
            ? $category->getKey()
            : $category;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name')
                    ->ignore($categoryId),
            ],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('categories', 'slug')
                    ->ignore($categoryId),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ];
    }
}
