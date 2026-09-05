<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ada laporan integritas baru — dan itu SELURUH isinya.
 *
 * **Tanpa jenis insiden, tanpa satu kata pun dari laporannya.** Bukan karena
 * lupa: halaman `/integrity` menjanjikan kerahasiaan kepada orang yang
 * melaporkan pengaturan skor atau pelecehan, dan surel keluar dari batas
 * sistem — ia mendarat di kotak masuk pihak ketiga, tersalin ke ponsel, muncul
 * di layar kunci, dan terbaca oleh siapa pun yang lewat di depan layar yang
 * terbuka. Isinya cukup dibaca di backoffice: di belakang login, 2FA, dan
 * daftar IP.
 *
 * Yang dikirim hanya sinyal "ada yang perlu dibuka" berikut tautannya. Itu
 * cukup untuk membuat laporan tidak mengendap berhari-hari, dan itu satu-
 * satunya hal yang pemberitahuan ini perlu lakukan.
 *
 * Kalau suatu saat isinya diputuskan ikut, keputusan itu harus tertulis
 * beserta alasannya — ia mengurangi janji yang dibuat halaman publik.
 *
 * `id` dibawa sebagai integer, bukan modelnya: tidak ada yang perlu
 * diserialisasi ke dalam antrean selain nomor barisnya, dan payload antrean
 * tersimpan di tabel `jobs` — tempat lain lagi yang tidak perlu memuat isi
 * laporan.
 */
class NewIntegrityReport extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reportId) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('backoffice.notifications.integrity.subject'))
            ->line(__('backoffice.notifications.integrity.intro'))
            ->line(__('backoffice.notifications.integrity.why_empty'))
            ->action(
                __('backoffice.notifications.integrity.action'),
                url('/integrity-reports/'.$this->reportId),
            );
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'integrity-reports',
            'title' => __('backoffice.notifications.integrity.bell_title'),
            'body' => __('backoffice.notifications.integrity.bell_body'),
            'url' => '/integrity-reports/'.$this->reportId,
        ];
    }
}
