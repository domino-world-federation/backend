<?php

namespace App\Notifications;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu pesan baru dari formulir `/contact`.
 *
 * **Isinya boleh ikut di surel**, tidak seperti laporan integritas. Ini surat
 * untuk dibaca, pengirimnya menuliskan namanya sendiri dan mengharapkan
 * balasan — merahasiakannya dari kotak masuk yang memang dituju berarti
 * memaksa orang membuka backoffice untuk tahu apakah sesuatu perlu dibuka.
 *
 * Yang dikirim CUPLIKAN, bukan seluruh badan pesan: 5000 karakter di dalam
 * pemberitahuan membuat surelnya jadi salinan kedua yang bisa dibalas orang
 * dengan tidak sengaja — dan balasan ke `no-reply@` tidak sampai ke mana pun.
 */
class NewContactMessage extends Notification implements ShouldQueue
{
    use Queueable;

    /** Panjang cuplikan yang sama dengan yang dipakai layar daftarnya. */
    private const EXCERPT = 200;

    public function __construct(public ContactMessage $message) {}

    /**
     * Lonceng hanya untuk yang punya akun.
     *
     * Kotak masuk bersama datang ke sini sebagai `AnonymousNotifiable` — ia
     * alamat surel, bukan baris di tabel `users`, jadi tidak ada yang bisa
     * menandainya terbaca. `NotificationSender` sebenarnya sudah melewati
     * kanal `database` untuk penerima anonim; disebut lagi di sini supaya kelas
     * ini menyatakan sendiri ke mana ia pergi, alih-alih benar karena ada
     * penjaga di tempat lain yang kebetulan masih ada.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('backoffice.notifications.contact.subject', ['topic' => $this->message->topic]))
            ->line(__('backoffice.notifications.contact.intro', [
                'name' => $this->message->name,
                'topic' => $this->message->topic,
            ]))
            ->line(__('backoffice.notifications.contact.from', ['email' => $this->message->email]))
            ->line('---')
            ->line(str($this->message->message)->limit(self::EXCERPT)->toString())
            ->action(
                __('backoffice.notifications.contact.action'),
                url('/contact-messages/'.$this->message->id),
            );
    }

    /**
     * Baris lonceng.
     *
     * `url` disimpan APA ADANYA, bukan dirakit ulang di sisi Vue: notifikasi
     * hidup lebih lama daripada rute, dan yang tersimpan harus tetap menunjuk
     * ke tempat yang benar walau menunya nanti dipindah.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'contact-messages',
            'title' => __('backoffice.notifications.contact.bell_title'),
            'body' => __('backoffice.notifications.contact.bell_body', [
                'name' => $this->message->name,
                'topic' => $this->message->topic,
            ]),
            'url' => '/contact-messages/'.$this->message->id,
        ];
    }
}
