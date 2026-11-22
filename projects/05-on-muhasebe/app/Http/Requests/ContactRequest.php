<?php

namespace App\Http\Requests;

use App\Enums\ContactType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ContactType::class)],
            'name' => ['required', 'string', 'max:150'],
            // Vergi kimlik numarası 10, TC kimlik numarası 11 hanedir
            'tax_number' => ['nullable', 'regex:/^(\d{10}|\d{11})$/'],
            'tax_office' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['tax_number.regex' => 'Vergi numarası 10, TC kimlik numarası 11 haneli olmalıdır.'];
    }
}
