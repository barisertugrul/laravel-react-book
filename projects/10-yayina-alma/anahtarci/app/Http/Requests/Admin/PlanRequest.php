<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route zaten role:admin ile korunuyor
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['sort' => $this->input('sort') ?? 0]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            // Fiyat metin olarak gelir ("1250,50"); MoneyCast kuruşa çevirir (Bölüm 5). Binlik ayıracı yok:
            // "1.250" hem 1250 TL hem 1,25 TL okunabilirdi.
            'price' => ['required', 'regex:/^\d{1,7}([.,]\d{1,2})?$/'],
            // Boş: sınırsız
            'max_sites' => ['nullable', 'integer', 'min:1', 'max:10000'],
            // Doluysa plan vitrinde vurgulanır ve bu etiketi taşır ("En çok tercih edilen"); boş: vurgu yok
            'badge' => ['nullable', 'string', 'max:40'],
            'sort' => ['integer', 'min:0', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['price.regex' => 'Fiyatı 1250,50 biçiminde girin (binlik ayıracı kullanmayın).'];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'plan adı', 'price' => 'fiyat', 'max_sites' => 'site sınırı', 'badge' => 'vitrin etiketi', 'sort' => 'sıra'];
    }
}
