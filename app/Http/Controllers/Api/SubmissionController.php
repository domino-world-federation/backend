<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\IntegrityReport;
use App\Models\NewsletterSubscriber;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Empat formulir situs publik yang MENULIS.
 *
 * Terpisah dari `PublicController`, yang cuma membaca. Bedanya bukan kerapian:
 * yang di sini di-throttle, memakai honeypot, dan tidak boleh di-cache oleh
 * apa pun — tiga hal yang tidak berlaku untuk satu pun endpoint baca.
 *
 * Semuanya membalas 204 tanpa isi. Tidak ada satu pun formulir di situs publik
 * yang menampilkan sesuatu dari responsnya, dan mengirim balik baris yang baru
 * dibuat berarti endpoint publik yang membocorkan id berurutan tanpa satu pun
 * alasan.
 */
class SubmissionController extends Controller
{
    /**
     * Nama field jebakan.
     *
     * Kosong berarti manusia. Bot mengisi setiap input yang ditemukannya, dan
     * field ini disembunyikan lewat CSS di sisi klien sehingga tidak ada
     * manusia yang melihatnya — termasuk pembaca layar, lewat `aria-hidden`.
     *
     * CATATAN: sampai situs publik benar-benar merender field ini, jebakannya
     * tidak menangkap apa pun. Ia dipasang lebih dulu supaya sisi frontend
     * cukup menambah satu `<input>` tanpa menunggu perubahan di sini.
     */
    private const HONEYPOT = 'website';

    public function contact(Request $request): JsonResponse
    {
        if ($this->trapped($request)) {
            return $this->accepted();
        }

        // Ejaan topiknya disamakan DULU: situs publik mengirim sentence case,
        // daftar CMS memakai title case, dan keduanya topik yang sama.
        $request->merge(['topic' => ContactMessage::canonicalTopic($request->input('topic'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'topic' => ['required', 'string', Rule::in(ContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'country' => ['nullable', 'string', 'max:120'],
            'subject' => ['nullable', 'string', 'max:200'],
        ]);

        ContactMessage::create($data);

        return $this->accepted();
    }

    /**
     * Mendaftar lagi dengan alamat yang sudah terdaftar membalas SUKSES.
     *
     * 422 di sini akan memberi tahu siapa pun yang mengetik sebuah alamat
     * apakah alamat itu ada di daftar — sebuah kebocoran, dan sekaligus pesan
     * galat untuk sesuatu yang bukan kesalahan orangnya. Yang sudah berhenti
     * berlangganan lalu mendaftar lagi memang dihidupkan kembali: itu yang
     * dimaksudnya.
     */
    public function newsletter(Request $request): JsonResponse
    {
        if ($this->trapped($request)) {
            return $this->accepted();
        }

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        NewsletterSubscriber::query()->updateOrCreate(
            ['email' => mb_strtolower($data['email'])],
            ['unsubscribed_at' => null],
        );

        return $this->accepted();
    }

    /**
     * "Notify me" di halaman turnamen.
     *
     * Turnamen yang belum tayang tidak bisa dilanggani — halamannya pun tidak
     * ada. `live()` yang menegakkannya, bukan sekadar 404 dari route model
     * binding, karena baris draft TETAP punya id yang bisa ditebak.
     */
    public function subscribeToTournament(Request $request, Tournament $tournament): JsonResponse
    {
        abort_unless($tournament->newQuery()->live()->whereKey($tournament->id)->exists(), 404);

        if ($this->trapped($request)) {
            return $this->accepted();
        }

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        TournamentNotification::query()->updateOrCreate([
            'tournament_id' => $tournament->id,
            'email' => mb_strtolower($data['email']),
        ]);

        return $this->accepted();
    }

    /**
     * Laporan integritas — anonim, dan tidak ada yang disimpan selain isinya.
     *
     * `min:20` sama persis dengan yang diperiksa formulirnya sendiri sebelum
     * mengirim. Dua pemeriksaan untuk aturan yang sama disengaja: yang di klien
     * memberi tahu seketika, yang di sini yang benar-benar menegakkan.
     */
    public function integrityReport(Request $request): JsonResponse
    {
        if ($this->trapped($request)) {
            return $this->accepted();
        }

        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(IntegrityReport::TYPES)],
            'description' => ['required', 'string', 'min:'.IntegrityReport::MIN_DESCRIPTION, 'max:10000'],
        ]);

        IntegrityReport::create($data);

        return $this->accepted();
    }

    /** Terisi = bot. Dibalas sukses, bukan galat: galat memberi tahu bot cara lolos. */
    private function trapped(Request $request): bool
    {
        return filled($request->input(self::HONEYPOT));
    }

    private function accepted(): JsonResponse
    {
        return response()->json(null, 204);
    }
}
