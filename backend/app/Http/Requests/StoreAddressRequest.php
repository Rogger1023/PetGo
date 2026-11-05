<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => [
                'required',
                'string',
                'max:120',
            ],

            'phone' => [
                'required',
                'string',
                'min:8',
                'max:20',
            ],

            'postal_code' => [
                'required',
                'string',
                'regex:/^[0-9]{5}-?[0-9]{3}$/',
            ],

            'street' => [
                'required',
                'string',
                'max:180',
            ],

            'number' => [
                'required',
                'string',
                'max:20',
            ],

            'complement' => [
                'nullable',
                'string',
                'max:120',
            ],

            'neighborhood' => [
                'required',
                'string',
                'max:100',
            ],

            'city' => [
                'required',
                'string',
                'max:100',
            ],

            'state' => [
                'required',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
            ],

            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_default' => [
                'sometimes',
                'boolean',
            ],
        ];
    }
}
