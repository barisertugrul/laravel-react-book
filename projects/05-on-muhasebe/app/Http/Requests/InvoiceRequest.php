<?php

namespace App\Http\Requests;

use App\Enums\InvoiceType;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'contact_id' => ['required', 'exists:contacts,id'],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:200'],
            // Ondalık ayıracı olarak virgül de kabul ediyoruz: "2,5".
            // Üst sınırlar, fiyat × miktar çarpımının PHP tam sayı sınırını (9,2 × 10¹⁸) aşmaması için.
            'lines.*.quantity' => ['required', 'regex:/^\d{1,5}([.,]\d{1,3})?$/', 'not_regex:/^0+([.,]0+)?$/'],
            'lines.*.unit_price' => ['required', 'regex:/^\d{1,8}([.,]\d{1,2})?$/'],
            'lines.*.discount_rate' => ['required', 'integer', 'between:0,100'],
            'lines.*.vat_rate' => ['required', Rule::in([0, 1, 10, 20])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lines.required' => 'Faturaya en az bir satır ekleyin.',
            'lines.*.quantity.regex' => 'Miktar en fazla 99999 ve en fazla 3 ondalık basamaklı olmalı (ör. 2,5).',
            'lines.*.quantity.not_regex' => 'Miktar sıfır olamaz.',
            'lines.*.unit_price.regex' => 'Birim fiyatı binlik ayıracı olmadan, en fazla 2 ondalıkla girin (ör. 1250,90).',
            'lines.*.vat_rate.in' => 'KDV oranı %0, %1, %10 veya %20 olabilir.',
        ];
    }

    public function invoiceData(): array
    {
        return $this->safe()->only(['type', 'contact_id', 'issue_date', 'due_date', 'notes']);
    }

    /** Satırlar, fiyatlar kuruşa çevrilmiş olarak */
    public function lines(): array
    {
        return array_map(fn (array $line) => [
            'description' => $line['description'],
            'quantity' => str_replace(',', '.', $line['quantity']),
            'unit_price' => Money::parse($line['unit_price']),
            'discount_rate' => (int) $line['discount_rate'],
            'vat_rate' => (int) $line['vat_rate'],
        ], array_values($this->validated('lines')));
    }
}
