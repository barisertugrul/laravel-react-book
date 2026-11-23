<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
{
    // İstek token'sız gelir; yetki lisans anahtarının kendisidir (bkz. ActivateLicense)
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'domain' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['key' => 'lisans anahtarı', 'domain' => 'site adresi'];
    }
}
