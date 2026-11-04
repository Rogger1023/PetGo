<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('categories', 'name'),
            ],
            'slug' => [
                'required',
                'string',
                'max:140',
                'alpha_dash',
                Rule::unique('categories', 'slug'),
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
