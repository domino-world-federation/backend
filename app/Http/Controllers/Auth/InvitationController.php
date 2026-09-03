<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menerima undangan admin — sisi publiknya (`529:9714`).
 *
 * DI LUAR grup `auth` dan `guest`, dan keduanya disengaja:
 * - `auth` akan menolak orang yang justru belum punya sandi untuk login.
 * - `guest` akan menolak super admin yang sedang menguji tautannya sendiri,
 *   dan penolakan itu terbaca sebagai "tautannya rusak".
 */
class InvitationController extends Controller
{
    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->find($token);

        if ($invitation === null) {
            return $this->rejected();
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'name' => $invitation->user->name,
            'email' => $invitation->user->email,
            'appName' => config('app.name'),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $invitation = $this->find($token);

        if ($invitation === null) {
            return $this->rejected();
        }

        /*
         * Transaksi, dan urutannya penting.
         *
         * Menandai undangan terpakai DI DALAM transaksi yang sama dengan
         * penulisan sandi menutup jendela di mana dua permintaan bersamaan
         * sama-sama lolos pemeriksaan `pending()` — yang kedua akan menimpa
         * sandi yang baru saja dipilih orang pertama.
         */
        DB::transaction(function () use ($invitation, $data) {
            $invitation->user->forceFill(['password' => $data['password']])->save();
            $invitation->update(['accepted_at' => now()]);

            activity('admin-invitation')
                ->performedOn($invitation->user)
                ->event('invitation_accepted')
                ->log('invitation_accepted');
        });

        // Langsung dilogin: orangnya baru saja membuktikan penguasaan atas
        // kotak surel DAN memilih sandinya sendiri. Memaksanya mengetik ulang
        // sandi yang dibuat sepuluh detik lalu tidak menambah satu pun
        // pemeriksaan. Kalau akunnya menuntut 2FA, middleware `auth` yang
        // mengarahkannya ke layar pendaftaran TOTP.
        Auth::login($invitation->user);
        $request->session()->regenerate();

        return to_route('dashboard')->with('success', __('backoffice.invitation.accepted'));
    }

    /**
     * Undangan yang sah untuk token ini, atau `null`.
     *
     * Dicari lewat HASH-nya — itu satu-satunya yang tersimpan. Akun nonaktif
     * ikut ditolak: undangan yang masih berlaku ke akun yang sudah dimatikan
     * adalah pintu yang dikira sudah tertutup.
     */
    private function find(string $token): ?AdminInvitation
    {
        $invitation = AdminInvitation::query()
            ->with('user')
            ->where('token_hash', AdminInvitation::hash($token))
            ->pending()
            ->first();

        if ($invitation === null || ! $invitation->user->is_active) {
            return null;
        }

        return $invitation;
    }

    /**
     * Satu jawaban untuk semua kegagalan — token asing, kedaluwarsa, dicabut,
     * sudah dipakai, atau akunnya dimatikan.
     *
     * Tidak dibedakan dengan sengaja. Pesan "undangan ini sudah dipakai"
     * memberi tahu penebak token bahwa tebakannya benar; pesan yang sama untuk
     * semuanya tidak memberi tahu apa pun.
     */
    private function rejected(): RedirectResponse
    {
        return to_route('login')->withErrors([
            'email' => __('backoffice.invitation.invalid'),
        ]);
    }
}
