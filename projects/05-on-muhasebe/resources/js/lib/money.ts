/**
 * Para işlemleri tarayıcıda da kuruş (tam sayı) ile yapılır.
 * Bu dosyadaki hesaplar app/Support/Money.php ve InvoiceCalculator.php ile birebir aynıdır;
 * ekrandaki canlı toplam ile sunucunun kaydettiği toplam bu sayede kuruşu kuruşuna tutar.
 */

const formatter = new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
});

/** 125050 → "₺1.250,50" */
export const formatMoney = (cents: number) => formatter.format(cents / 100);

/**
 * "1250,50" | "1250.5" | "1250" → 125050; geçersizse null.
 * Sunucudaki doğrulama kuralıyla aynı biçimi kabul eder (binlik ayıracı yok).
 */
export function parseMoney(input: string): number | null {
    const match = input.trim().match(/^(\d{1,8})(?:[.,](\d{1,2}))?$/);
    if (!match) {
        return null;
    }

    return Number(match[1]) * 100 + Number((match[2] ?? '').padEnd(2, '0'));
}

/** "2,5" → 2500 (binde bir); geçersizse null */
export function parseQuantity(input: string): number | null {
    const match = input.trim().match(/^(\d{1,5})(?:[.,](\d{1,3}))?$/);
    if (!match) {
        return null;
    }

    return Number(match[1]) * 1000 + Number((match[2] ?? '').padEnd(3, '0'));
}

/**
 * Bölüp yarım değerde sıfırdan uzağa yuvarlar (PHP'deki Money::divideRounded).
 * BigInt kullanıyoruz: fiyat × miktar 10¹⁷ düzeyine çıkabilir, JavaScript'in Number tipi ise
 * 2⁵³ (≈ 9 × 10¹⁵) üzerindeki tam sayıları kesin tutamaz.
 */
export function divideRounded(value: bigint, divisor: bigint): number {
    let quotient = value / divisor; // BigInt bölmesi sıfıra doğru keser, PHP'deki intdiv gibi
    const remainder = value % divisor;
    const abs = remainder < 0n ? -remainder : remainder;
    if (abs * 2n >= divisor) {
        quotient += value < 0n ? -1n : 1n;
    }

    return Number(quotient);
}

export type LineInput = {
    quantity: string;
    unit_price: string;
    discount_rate: string;
    vat_rate: string;
};
export type LineTotals = {
    gross: number;
    discount: number;
    net: number;
    vat: number;
    total: number;
};

export function calculateLine(line: LineInput): LineTotals | null {
    const qty = parseQuantity(line.quantity);
    const price = parseMoney(line.unit_price);
    if (qty === null || price === null) {
        return null;
    }
    const gross = divideRounded(BigInt(price) * BigInt(qty), 1000n);
    const discount = divideRounded(
        BigInt(gross) * BigInt(line.discount_rate || 0),
        100n,
    );
    const net = gross - discount;
    const vat = divideRounded(BigInt(net) * BigInt(line.vat_rate), 100n);

    return { gross, discount, net, vat, total: net + vat };
}

export function calculateTotals(lines: LineInput[]) {
    const totals = {
        subtotal: 0,
        discount_total: 0,
        vat_total: 0,
        total: 0,
        vat_breakdown: {} as Record<string, number>,
    };

    for (const line of lines) {
        const t = calculateLine(line);
        if (!t) {
            continue;
        }
        totals.subtotal += t.gross;
        totals.discount_total += t.discount;
        totals.vat_total += t.vat;
        totals.total += t.total;
        totals.vat_breakdown[line.vat_rate] =
            (totals.vat_breakdown[line.vat_rate] ?? 0) + t.vat;
    }

    return totals;
}
