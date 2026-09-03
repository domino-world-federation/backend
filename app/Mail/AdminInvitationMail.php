<?php

namespace App\Mail;

use App\Models\AdminInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tautan undangan admin — panel "Invitation Flow" (`529:9714`).
 *
 * Token MENTAH diterima lewat constructor dan tidak pernah dibaca dari model:
 * yang tersimpan cuma hash-nya, dan itu memang tidak bisa dikembalikan. Karena
 * itu surel ini hanya bisa disusun pada saat undangan diterbitkan atau dikirim
 * ulang — tidak ada jalan untuk "mengirim ulang yang lama" tanpa menerbitkan
 * token baru, dan itu perilaku yang benar.
 */
class AdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AdminInvitation $invitation,
        public string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('backoffice.invitation.mail_subject', [
                'app' => config('app.name'),
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin-invitation',
            with: [
                'name' => $this->invitation->user->name,
                'url' => url("/invitation/{$this->token}"),
                'expiresAt' => $this->invitation->expires_at,
                'hours' => AdminInvitation::TTL_HOURS,
            ],
        );
    }
}
