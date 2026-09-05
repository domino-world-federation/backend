<?php

namespace App\Support;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Siapa yang diberi tahu saat formulir situs publik masuk.
 *
 * Dua penerima yang berbeda sifatnya, dan itu sengaja dipisah:
 *
 *   1. **Kotak masuk bersama** — satu alamat surel milik federasi
 *      (`form_recipient_email` di Site Settings). Ia tidak punya akun, tidak
 *      punya lonceng, dan tidak perlu punya: yang membacanya bisa berganti
 *      orang tanpa ada yang mengubah apa pun di sini.
 *   2. **Admin yang memang boleh membacanya** — mereka yang dapat lonceng dan
 *      badge di sidebar.
 *
 * Yang kedua disaring lewat `can()`, BUKAN lewat query izin Spatie. Alasannya
 * `super-admin`: izinnya tidak pernah tercatat di tabel, ia dilewatkan
 * `Gate::before` (lihat `Access::roles()`), jadi query `User::permission(...)`
 * akan melewatkan justru orang yang paling pasti berhak. Tabel pengguna di sini
 * berisi admin saja — puluhan, bukan jutaan — jadi menyaringnya di PHP membaca
 * gerbang yang SAMA dengan yang menyembunyikan menunya di sidebar. Dua jalur
 * berbeda untuk satu pertanyaan "boleh lihat?" adalah dua jawaban yang menunggu
 * berbeda.
 */
final class SubmissionRecipients
{
    /**
     * Alamat kotak masuk bersama, atau `null` kalau tidak ada.
     *
     * Site Settings lebih dulu, `.env` sebagai cadangan. Urutannya begitu
     * karena yang di Site Settings bisa diubah orang yang memang mengurus
     * kotak masuknya, tanpa akses server.
     */
    public static function sharedInbox(): ?string
    {
        $configured = SiteSetting::map(SiteSetting::GROUP_CONTACT)['form_recipient_email'] ?? null;

        $address = filled($configured) ? $configured : config('mail.submissions_to');

        return filled($address) ? $address : null;
    }

    /**
     * Admin yang boleh membuka modul ini.
     *
     * @return Collection<int, User>
     */
    public static function admins(string $permission): Collection
    {
        return User::query()
            // Akun yang dinonaktifkan tidak bisa masuk, jadi loncengnya tidak
            // akan pernah dibuka dan surelnya jatuh ke bekas karyawan.
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can($permission))
            ->values();
    }
}
