<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStatusRequest extends FormRequest
{
    /** Yetki kontrolü Form Request'in içinde de yapılabilir. */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'))
            || $this->user()->can('claim', $this->route('ticket'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return [
            // Yalnızca mevcut durumdan geçilebilecek durumlar kabul edilir
            'status' => ['required', Rule::enum(TicketStatus::class)->only($ticket->status->transitions())],
            // İptal ve "hazır → tamire geri" gibi geçişlerde gerekçe zorunlu
            'note' => [
                Rule::requiredIf(fn () => $ticket->status->requiresReason(
                    TicketStatus::tryFrom((string) $this->input('status')) ?? $ticket->status,
                )),
                'nullable', 'string', 'max:500',
            ],
            'is_public' => ['boolean'],
            'final_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.enum' => 'Bu kayıt şu anki durumundan seçilen duruma geçemez.',
            'note.required' => 'Bu durum değişikliği için bir gerekçe yazın.',
        ];
    }
}
