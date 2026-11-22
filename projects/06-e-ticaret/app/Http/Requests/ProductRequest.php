<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route zaten role:admin ile korunuyor
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Düzenlenen ürünün kendi slug'ı "zaten alınmış" sayılmasın
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:140', Rule::unique('products')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            'stock' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['price.regex' => 'Fiyatı 349,90 biçiminde girin.'];
    }
}
