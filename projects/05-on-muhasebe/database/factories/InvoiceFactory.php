<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Contact;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Testler için basit bir fatura: 1.000,00 ₺ + %20 KDV = 1.200,00 ₺.
 * Satırlı ve gerçek hesaplı faturalar için CreateInvoice action'ını kullanın.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => InvoiceType::Sales,
            'number' => 'SAT-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'contact_id' => Contact::factory(),
            'issue_date' => today(),
            'due_date' => today()->addDays(30),
            'subtotal' => 100000,
            'discount_total' => 0,
            'vat_total' => 20000,
            'total' => 120000,
            'paid_total' => 0,
        ];
    }

    public function overdue(int $days = 10): static
    {
        return $this->state(['issue_date' => today()->subDays($days + 30), 'due_date' => today()->subDays($days)]);
    }
}
