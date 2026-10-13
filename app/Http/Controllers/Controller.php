<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    /**
     * Ke mana sesudah menghapus sebuah baris.
     *
     * `back()` benar saat penghapusan dilakukan dari DAFTAR — filter dan
     * halamannya tetap. Tapi dari layar BACA barisnya, `back()` kembali ke
     * layar yang barusan dihapus, dan yang tampil 404 (2026-10-13). Kalau
     * halaman sebelumnya adalah barisnya sendiri (`/news/12`, `/news/12/edit`),
     * yang dituju daftarnya.
     */
    protected function afterDelete(string $recordPath, string $indexRoute): RedirectResponse
    {
        $previous = parse_url(url()->previous(), PHP_URL_PATH) ?: '';
        $record = '/'.trim($recordPath, '/');

        return $previous === $record || str_starts_with($previous, $record.'/')
            ? to_route($indexRoute)
            : back();
    }
}
