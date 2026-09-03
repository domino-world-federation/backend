<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Mencatat kejadian otentikasi ke log aktivitas yang sama.
 *
 * Empat kejadian, bukan dua. Login dan logout yang diminta, tapi log otentikasi
 * yang HANYA mencatat keberhasilan tidak memberi tahu apa pun saat terjadi
 * sesuatu: yang dicari orang justru "siapa yang gagal masuk berkali-kali, dari
 * mana" — dan itu `failed` dan `lockout`.
 *
 * IP dan user agent ikut dicatat. Tanpa keduanya, entri "admin masuk" tidak
 * bisa dibedakan dari "seseorang dengan sandi admin masuk dari negara lain".
 */
class RecordAuthActivity
{
    public const LOG_NAME = 'authentication';

    /*
     * Method-nya sengaja bernama `on…`, BUKAN `handle…`.
     *
     * Laravel memindai `app/Listeners` dan mendaftarkan sendiri tiap method
     * bernama `handle*` yang type-hint sebuah event. Dengan nama itu, keempat
     * kejadian terdaftar DUA KALI — sekali otomatis, sekali dari
     * `AppServiceProvider` — dan setiap login menghasilkan dua baris log yang
     * identik. Tidak ada galat; cuma jejak audit yang menghitung ganda.
     */

    public function onLogin(Login $event): void
    {
        $this->write('login', $event->user);

        /*
         * Kolom "Last Login" (`528:8821`) diisi DI SINI, bukan di controller.
         *
         * Login terjadi lewat lebih dari satu pintu — formulir biasa, layar
         * 2FA, dan penerimaan undangan — dan ketiganya menembakkan event yang
         * sama. Menitipkannya ke masing-masing controller berarti suatu saat
         * ada pintu baru yang lupa, dan yang terlihat di daftar adalah admin
         * aktif yang "belum pernah login".
         *
         * `updateQuietly`: kolom ini berubah tiap kali orang masuk, dan
         * mencatatnya sebagai perubahan model akan menenggelamkan jejak audit
         * di bawah baris yang tidak menjawab pertanyaan siapa pun.
         */
        $event->user->forceFill(['last_login_at' => now()])->updateQuietly();
    }

    public function onLogout(Logout $event): void
    {
        // Bisa null kalau logout dipanggil tanpa sesi aktif — dan itu memang
        // terjadi: route logout sengaja di luar `auth` supaya pengguna yang
        // berhenti di layar 2FA bisa membatalkan.
        if ($event->user !== null) {
            $this->write('logout', $event->user);
        }
    }

    public function onFailed(Failed $event): void
    {
        $this->write('failed', $event->user, [
            // HANYA emailnya. `$event->credentials` memuat kata sandi yang
            // barusan dicoba — menuliskannya ke log audit berarti menumpuk
            // tebakan sandi dalam bentuk teks polos, dan tebakan yang nyaris
            // benar jauh lebih berharga bagi penyerang daripada hash.
            'email' => $event->credentials['email'] ?? null,
        ]);
    }

    public function onLockout(Lockout $event): void
    {
        $this->write('lockout', null, [
            'email' => $event->request->input('email'),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function write(string $event, mixed $user, array $extra = []): void
    {
        $request = request();

        $activity = activity(self::LOG_NAME)
            ->event($event)
            ->withProperties([
                'ip' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request ? $request->userAgent() : null,
                ...$extra,
            ]);

        if ($user instanceof User) {
            // `causedBy` DAN `performedOn`: pelakunya dan subjeknya orang yang
            // sama, dan kolom "Record" membaca subjeknya.
            $activity->causedBy($user)->performedOn($user);
        }

        $activity->log($event);
    }
}
