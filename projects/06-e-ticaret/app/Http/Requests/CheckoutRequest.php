<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shipping_name' => ['required', 'string', 'max:100'],
            // 05xx xxx xx xx: boşluk, tire ve parantez serbest
            'shipping_phone' => ['required', 'string', 'regex:/^[0-9 ()+-]{10,20}$/'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:50'],
        ];
    }
}
