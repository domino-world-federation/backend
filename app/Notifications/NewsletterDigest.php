<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ringkasan pendaftar buletin — SEHARI SEKALI, bukan satu surel per pendaftar.
 *
 * Itu bedanya dengan dua notifikasi lain di folder ini, dan bedanya bukan
 * selera. Pesan kontak dan laporan integritas adalah sesuatu yang harus
 * DIKERJAKAN seseorang; berlangganan buletin tidak — tidak ada yang perlu
 * dibalas, dibaca, atau diputuskan. Satu surel per orang yang berlangganan
 * akan melatih penerimanya mengabaikan seluruh pemberitahuan dari sistem ini,
 * dan yang ikut terabaikan nanti adalah laporan integritas.
 *
 * Dikirim `dwf:newsletter-digest`, dan HANYA kalau angkanya bukan nol —
 * "0 pendaftar baru hari ini" adalah surel yang tidak memberi tahu apa pun
 * sambil menuntut dibuka.
 */
class NewsletterDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $newSubscribers,
        public int $totalSubscribers,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('backoffice.notifications.newsletter.subject', ['count' => $this->newSubscribers]))
            ->line(__('backoffice.notifications.newsletter.intro', [
                'count' => $this->newSubscribers,
                'total' => $this->totalSubscribers,
            ]))
            ->action(__('backoffice.notifications.newsletter.action'), url('/newsletter'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'module' => 'newsletter',
            'title' => __('backoffice.notifications.newsletter.bell_title'),
            'body' => __('backoffice.notifications.newsletter.bell_body', [
                'count' => $this->newSubscribers,
            ]),
            'url' => '/newsletter',
        ];
    }
}
