{{--
    Surel undangan admin. Markdown mailable bawaan Laravel, bukan template
    sendiri: yang dikirim ke luar harus tetap terbaca di klien surel yang tidak
    memuat CSS, dan komponen bawaannya sudah menangani itu.
--}}
<x-mail::message>
# {{ __('backoffice.invitation.mail_heading', ['app' => config('app.name')]) }}

{{ __('backoffice.invitation.mail_greeting', ['name' => $name]) }}

{{ __('backoffice.invitation.mail_body', ['hours' => $hours]) }}

<x-mail::button :url="$url">
{{ __('backoffice.invitation.mail_button') }}
</x-mail::button>

{{ __('backoffice.invitation.mail_expiry', ['at' => $expiresAt->format('d/m/Y H:i')]) }}

{{ __('backoffice.invitation.mail_ignore') }}

{{-- Tautan mentah ikut dicetak: sebagian klien surel memblokir tombol, dan
     undangan yang tidak bisa diklik tidak punya jalan lain. --}}
<x-mail::subcopy>
{{ __('backoffice.invitation.mail_fallback') }} [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>
