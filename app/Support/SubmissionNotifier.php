<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as Notifier;
use Throwable;

/**
 * Mengirim pemberitahuan formulir situs publik ke dua penerima sekaligus.
 *
 * Dipakai `SubmissionController` dan `dwf:newsletter-digest`, jadi keduanya
 * memilih penerima dengan aturan yang sama — dan ketika aturannya berubah, ia
 * berubah sekali.
 *
 * **Gagal mengirim tidak boleh menggagalkan formulirnya.** Barisnya sudah
 * tersimpan sebelum baris ini dipanggil; kalau antrean, basis data, atau SMTP
 * bermasalah, yang benar adalah situs publik tetap membalas 204 dan
 * kegagalannya masuk log. Kebalikannya — 500 kepada orang yang pesannya
 * SUDAH tersimpan — membuat mereka mengirim ulang pesan yang sebenarnya
 * sampai, dan tetap tidak memberi tahu siapa pun bahwa surelnya tidak keluar.
 *
 * Notifikasinya sendiri `ShouldQueue`, jadi yang terjadi di sini cuma satu
 * baris masuk ke tabel `jobs`. **Tanpa `php artisan queue:work` yang hidup,
 * tidak ada satu pun surel yang keluar** — dan itu tidak terlihat di layar
 * mana pun, jadi ia dicatat di sini dan di docs/SISA-PEKERJAAN.md.
 */
final class SubmissionNotifier
{
    /**
     * @param  string  $permission  Izin yang menentukan admin mana yang diberi tahu — modul yang sama dengan yang boleh membukanya.
     */
    public static function send(string $permission, Notification $notification): void
    {
        try {
            $inbox = SubmissionRecipients::sharedInbox();

            if ($inbox !== null) {
                Notifier::route('mail', $inbox)->notify($notification);
            }

            $admins = SubmissionRecipients::admins($permission);

            if ($admins->isNotEmpty()) {
                Notifier::send($admins, $notification);
            }
        } catch (Throwable $e) {
            Log::error('Pemberitahuan submission gagal dikirim.', [
                'notification' => $notification::class,
                'permission' => $permission,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
