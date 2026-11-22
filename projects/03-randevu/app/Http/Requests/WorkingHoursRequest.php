<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7', 'distinct'],
            'hours.*.is_open' => ['boolean'],
            'hours.*.opens_at' => ['required', 'date_format:H:i'],
            'hours.*.closes_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Kurallara sığmayan kontroller: kapanış saati açılıştan sonra olmalı.
     * "after" hook'u, temel kurallar geçtikten sonra çalışır.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->input('hours', []) as $i => $day) {
                    if (($day['is_open'] ?? false) && $day['closes_at'] <= $day['opens_at']) {
                        $validator->errors()->add("hours.$i.closes_at", 'Kapanış saati açılış saatinden sonra olmalıdır.');
                    }
                }
            },
        ];
    }
}
