<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lonceng di topbar — menandai terbaca, tidak lebih.
 *
 * Tidak ada layar daftar dan tidak ada izin sendiri: notifikasi milik satu
 * orang, dan yang boleh membacanya cuma orang itu. Semua aksi di sini bekerja
 * lewat relasi `$request->user()->notifications()`, jadi id milik orang lain
 * tidak ditemukan — bukan ditolak 403, melainkan memang tidak ada di sana.
 * Bedanya penting: 403 memberi tahu bahwa id itu ADA.
 */
class NotificationController extends Controller
{
    /**
     * Buka sebuah notifikasi.
     *
     * Menandai terbaca lalu MENGALIHKAN ke tujuannya. Sengaja satu langkah:
     * kalau menandai terbaca dan membuka barisnya adalah dua aksi terpisah,
     * lonceng akan menumpuk notifikasi yang sudah dikerjakan orangnya.
     *
     * Tujuannya diambil dari `data.url` yang tersimpan, bukan dirakit di sini,
     * dan diperiksa harus berupa path internal — data notifikasi ditulis
     * aplikasi ini sendiri, tapi ia bertahan lebih lama daripada kode yang
     * menulisnya, dan redirect terbuka adalah lubang yang mahal untuk satu
     * baris yang dilupakan.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $row = $request->user()->notifications()->whereKey($notification)->firstOrFail();

        $row->markAsRead();

        $url = (string) ($row->data['url'] ?? '/dashboard');

        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/dashboard');
    }

    /** Menandai seluruhnya terbaca, tanpa membuka apa pun. */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
