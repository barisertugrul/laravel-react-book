<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,12}([.,]\d{1,2})?$/', 'not_regex:/^0+([.,]0+)?$/'],
            'paid_at' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.regex' => 'Tutarı 1250,50 biçiminde girin.',
            'amount.not_regex' => 'Tutar sıfır olamaz.',
            'paid_at.before_or_equal' => 'İleri tarihli ödeme girilemez.',
        ];
    }

    public function amountInCents(): int
    {
        return Money::parse($this->validated('amount'));
    }
}
