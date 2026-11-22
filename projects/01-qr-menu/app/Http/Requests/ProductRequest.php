<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'category_id.required' => 'Bir kategori seçmelisiniz.',
            'category_id.exists' => 'Seçilen kategori bulunamadı.',
            'name.required' => 'Ürün adı zorunludur.',
            'price.required' => 'Fiyat zorunludur.',
            'price.numeric' => 'Fiyat sayı olmalıdır (ör. 149.90).',
            'price.min' => 'Fiyat negatif olamaz.',
            'image.image' => 'Yüklenen dosya bir görsel olmalıdır.',
            'image.mimes' => 'Görsel JPG, PNG veya WEBP olmalıdır.',
            'image.max' => 'Görsel en fazla 2 MB olabilir.',
        ];
    }

    public function toModelData(): array
    {
        return [
            'category_id' => $this->integer('category_id'),
            'name' => $this->string('name')->trim()->toString(),
            'description' => $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            'price' => $this->input('price'),
            'is_available' => $this->boolean('is_available'),
            'sort_order' => $this->integer('sort_order'),
        ];
    }
}
