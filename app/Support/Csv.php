<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV untuk layar daftar.
 *
 * Ada sebagai satu tempat karena enam modul mengekspor, dan tiga hal di
 * bawah ini akan berbeda-beda kalau tiap controller menulisnya sendiri:
 *
 * 1. **BOM UTF-8.** Tanpa itu Excel di Windows membaca judul beraksen sebagai
 *    sampah, dan yang disalahkan selalu datanya.
 * 2. **Streaming, bukan dikumpulkan di memori.** Daftar berita bisa ribuan
 *    baris; membangun seluruh berkas sebagai string lebih dulu berarti ekspor
 *    berhenti bekerja tepat saat datanya cukup banyak untuk perlu diekspor.
 * 3. **Nama berkas bertanggal.** Dua unduhan di hari berbeda tidak boleh
 *    bernama sama di folder Downloads yang sama.
 */
final class Csv
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function stream(string $prefix, array $headers, iterable $rows): StreamedResponse
    {
        $filename = $prefix.'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
