<?php

namespace Tests\Unit;

use App\Support\InvoiceCalculator;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public static function parseCases(): array
    {
        return [
            'tam sayı metin' => ['1250', 125000],
            'virgüllü' => ['149,90', 14990],
            'noktalı' => ['149.90', 14990],
            'tek ondalık' => ['2,5', 250],
            'binlik ayıraçlı' => ['1.250,50', 125050],
            'int lira' => [12, 1200],
            'eksi' => ['-10,05', -1005],
        ];
    }

    #[DataProvider('parseCases')]
    public function test_parse(string|int $input, int $expected): void
    {
        $this->assertSame($expected, Money::parse($input));
    }

    public function test_parse_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parse('12,345');
    }

    public function test_format(): void
    {
        $this->assertSame('1.250,50 ₺', Money::format(125050));
        $this->assertSame('0,05 ₺', Money::format(5));
        $this->assertSame('-1.000.000,00 ₺', Money::format(-100000000));
    }

    public function test_half_cents_round_away_from_zero(): void
    {
        $this->assertSame(3, Money::divideRounded(5, 2));   // 2,5 → 3
        $this->assertSame(2, Money::divideRounded(9, 4));   // 2,25 → 2
        $this->assertSame(-3, Money::divideRounded(-5, 2)); // -2,5 → -3
        $this->assertSame(60, Money::percentOf(333, 18));   // 59,94 → 60
    }

    public function test_quantity_is_parsed_in_thousandths(): void
    {
        $this->assertSame(2500, Money::parseQuantity('2,5'));
        $this->assertSame(1000, Money::parseQuantity('1'));
        $this->assertSame(125, Money::parseQuantity('0.125'));
    }

    public function test_line_calculation(): void
    {
        // 3 × 1.250,00 = 3.750,00; %10 iskonto = 375,00; matrah 3.375,00; %20 KDV = 675,00
        $this->assertSame(
            ['gross' => 375000, 'discount' => 37500, 'net' => 337500, 'vat' => 67500, 'total' => 405000],
            InvoiceCalculator::line(['quantity' => '3', 'unit_price' => 125000, 'discount_rate' => 10, 'vat_rate' => 20]),
        );
    }

    public function test_totals_are_the_sum_of_rounded_lines_and_vat_is_split_by_rate(): void
    {
        $totals = InvoiceCalculator::totals([
            ['quantity' => '1', 'unit_price' => 14990, 'discount_rate' => 0, 'vat_rate' => 20], // KDV 29,98
            ['quantity' => '3', 'unit_price' => 3333, 'discount_rate' => 0, 'vat_rate' => 10],  // 99,99; KDV 9,999 → 10,00
        ]);

        $this->assertSame(24989, $totals['subtotal']);
        $this->assertSame([10 => 1000, 20 => 2998], $totals['vat_breakdown']);
        $this->assertSame(3998, $totals['vat_total']);
        $this->assertSame(28987, $totals['total']);
    }
}
