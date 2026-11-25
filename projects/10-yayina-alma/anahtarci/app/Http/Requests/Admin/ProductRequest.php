<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            // slug lisans anahtarının İÇİNE yazılır; WordPress eklentisi kendi ürününü bununla tanır
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/', Rule::unique('products')->ignore($this->route('product'))],
            'summary' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator) {
            $product = $this->route('product');

            // Satılmış anahtarlarda eski slug yazılı: değişirse o lisanslar ürününü kaybeder
            if ($product instanceof Product && $this->input('slug') !== $product->slug && $product->licenses()->exists()) {
                $validator->errors()->add('slug', 'Bu ürün için lisans satıldı; adresi (slug) artık değiştirilemez.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['slug.regex' => 'Yalnızca küçük harf, rakam ve tire kullanın (ör. ajans-asistani).'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'ürün adı', 'slug' => 'adres', 'summary' => 'kısa açıklama', 'description' => 'açıklama'];
    }
}
