<?php

namespace App\Actions;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Support\InvoiceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateInvoice
{
    /**
     * @param  array{type: string, contact_id: int, issue_date: string, due_date: string, notes?: string|null}  $data
     * @param  list<array{description: string, quantity: string, unit_price: int, discount_rate: int, vat_rate: int}>  $lines  unit_price kuruş cinsinden
     */
    public function handle(array $data, array $lines): Invoice
    {
        $type = InvoiceType::from($data['type']);
        $year = CarbonImmutable::parse($data['issue_date'])->year;

        // Fatura numaraları boşluksuz ve sıralı olmalı. İki fatura aynı anda kesilirse
        // aynı numarayı almasınlar diye numara üretimi ve kayıt kilit altında yapılır.
        return Cache::lock("invoice-number:{$type->value}:{$year}", 10)->block(5, function () use ($type, $year, $data, $lines) {
            return DB::transaction(function () use ($type, $year, $data, $lines) {
                $totals = InvoiceCalculator::totals($lines);

                $invoice = Invoice::create([
                    ...$data,
                    'number' => $this->nextNumber($type, $year),
                    'subtotal' => $totals['subtotal'],
                    'discount_total' => $totals['discount_total'],
                    'vat_total' => $totals['vat_total'],
                    'total' => $totals['total'],
                ]);

                foreach (array_values($lines) as $i => $line) {
                    $calc = InvoiceCalculator::line($line);
                    $invoice->lines()->create([
                        ...$line,
                        'net' => $calc['net'],
                        'vat' => $calc['vat'],
                        'total' => $calc['total'],
                        'sort_order' => $i,
                    ]);
                }

                return $invoice;
            });
        });
    }

    /** SAT-2026-0041 → SAT-2026-0042; yıl değişince 0001'den başlar */
    private function nextNumber(InvoiceType $type, int $year): string
    {
        $prefix = "{$type->prefix()}-{$year}-";
        $last = Invoice::where('number', 'like', $prefix.'%')->max('number');
        $next = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
