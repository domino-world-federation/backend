<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\ProfileRequest;
use App\Models\User;
use App\Support\Media\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Akun sendiri — nama, surel, avatar, dan sandi.
 *
 * ── Kenapa TANPA `can:` ──
 *
 * Tiap route modul di aplikasi ini dijaga izin, dan `WritePermissionTest`
 * menegakkannya. Yang ini pengecualian yang disengaja: ia bukan modul, dan yang
 * disunting selalu `$request->user()` sendiri. Menjaganya dengan `users.update`
 * akan berarti seorang `viewer` tidak bisa mengganti sandinya sendiri —
 * sementara `users.update` justru izin untuk menyunting akun ORANG LAIN, yang
 * tidak ada hubungannya.
 *
 * ── Yang TIDAK bisa disentuh dari sini, dan itu yang membuatnya aman ──
 *
 * `roles`, `is_active`, `two_factor_enabled`, dan `member_federation_id` tidak
 * pernah dibaca dari permintaan. Semuanya keputusan tentang WEWENANG, dan
 * layar yang membiarkan orang menaikkan wewenangnya sendiri adalah layar yang
 * membatalkan seluruh tabel izin. Yang dipakai `fill()` di sini daftar putih
 * yang ditulis tangan, bukan `$request->validated()` apa adanya — atribut
 * `#[Fillable]` di model memuat keempatnya karena User Management memang
 * membutuhkannya, jadi mengandalkannya di sini akan meloloskan semuanya.
 * `ProfileTest` mengirim keempatnya dan memastikan tidak satu pun berubah.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatarUrl' => $user->avatar_url,
            ],

            /*
             * Fakta yang HANYA dibaca, dan ditampilkan justru karena tidak bisa
             * diubah di sini.
             *
             * Tanpanya layar ini memunculkan pertanyaan yang jawabannya ada di
             * tempat lain — "peran saya apa", "2FA saya sudah aktif belum" —
             * dan orang akan mencarinya dengan menekan-nekan field yang ada.
             */
            'account' => [
                'roles' => $user->getRoleNames()->all(),
                'twoFactor' => $user->mfa_status,
                'lastLoginAt' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Daftar putih yang ditulis tangan. Lihat komentar kelas.
        $user->fill([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
        ]);

        if ($request->hasFile('avatar')) {
            $user->avatar_path = StoredFile::put(
                $request->file('avatar'),
                'avatars',
                replacing: $user->avatar_path,
            );
        }

        // Kotak centang terpisah, bukan "unggah berkas kosong": tidak ada cara
        // lain menyatakan "buang avatar saya" lewat sebuah input berkas.
        if ($request->boolean('remove_avatar') && ! $request->hasFile('avatar')) {
            StoredFile::forget($user->avatar_path);
            $user->avatar_path = null;
        }

        $user->save();

        return back()->with('success', __('backoffice.profile.saved'));
    }

    /**
     * Ganti sandi — dan sandi LAMA wajib ikut.
     *
     * Bukan formalitas: sesi backoffice bertahan berjam-jam, jadi layar yang
     * terbuka di laptop yang ditinggal adalah jalan mengambil alih akun secara
     * permanen. Meminta sandi lama membuat penguasaan atas kursi orang lain
     * tidak cukup.
     *
     * Dipisah dari `update()` supaya galat "sandi lama salah" tidak ikut
     * membatalkan penggantian nama yang benar di formulir yang sama.
     */
    public function password(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        // `forceFill`: `password` ada di `#[Fillable]`, tapi menulisnya lewat
        // jalur yang sama dengan nama dan surel mengaburkan bahwa yang ini
        // menuntut pembuktian lebih dulu.
        $user->forceFill(['password' => $request->string('password')->toString()])->save();

        /*
         * Sesi digenerasi ulang. Bukan pencegahan fiksasi — orangnya sudah
         * login — melainkan supaya id sesi yang mungkin sudah bocor tidak
         * membawa serta sandi yang baru saja diganti untuk menyelamatkannya.
         *
         * Perangkat LAIN tidak ikut dikeluarkan, dan itu keputusan: driver sesi
         * `database` tanpa middleware `AuthenticateSession` tidak punya jalan
         * membatalkan sesi lain, dan menjanjikannya di layar tanpa
         * mengerjakannya lebih buruk daripada diam.
         */
        $request->session()->regenerate();

        activity('user')
            ->causedBy($user)
            ->performedOn($user)
            ->event('password_changed')
            ->log('password_changed');

        return back()->with('success', __('backoffice.profile.password_saved'));
    }
}
