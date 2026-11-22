<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9 +()\-]{10,}$/'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'device_type' => ['required', 'string', 'max:40'],
            'brand' => ['required', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:80'],
            'serial_number' => ['nullable', 'string', 'max:80'],
            'problem' => ['required', 'string', 'max:1000'],
            'accessories' => ['nullable', 'string', 'max:200'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function ticketData(): array
    {
        return $this->safe()->except(['customer_name', 'customer_phone', 'customer_email']);
    }
}
