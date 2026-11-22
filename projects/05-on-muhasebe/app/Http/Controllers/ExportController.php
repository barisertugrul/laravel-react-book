<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Faturaları Excel'de açılabilen bir CSV dosyası olarak indirir.
 * Ek paket gerektirmez; gerçek .xlsx için Alıştırma 5'e bakın.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $query = InvoiceController::filtered($request)->with('contact:id,name')->orderBy('issue_date');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM: Excel'in Türkçe karakterleri doğru okuması için
            fwrite($out, "\xEF\xBB\xBF");

            // Türkçe Excel, CSV'de ayraç olarak noktalı virgül bekler
            fputcsv($out, ['Numara', 'Tür', 'Cari', 'Tarih', 'Vade', 'Matrah', 'KDV', 'Toplam', 'Ödenen', 'Kalan', 'Durum'], ';');

            // lazy(): binlerce faturayı belleğe tek seferde yüklemeden, parça parça okur
            foreach ($query->lazy(500) as $i) {
                fputcsv($out, [
                    $i->number,
                    $i->type->label(),
                    $i->contact->name,
                    $i->issue_date->format('d.m.Y'),
                    $i->due_date->format('d.m.Y'),
                    self::decimal($i->subtotal - $i->discount_total),
                    self::decimal($i->vat_total),
                    self::decimal($i->total),
                    self::decimal($i->paid_total),
                    self::decimal($i->remaining),
                    $i->status,
                ], ';');
            }

            fclose($out);
        }, 'faturalar-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 125050 → "1250,50": Türkçe Excel'in sayı olarak tanıdığı biçim */
    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100).','.str_pad((string) abs($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
