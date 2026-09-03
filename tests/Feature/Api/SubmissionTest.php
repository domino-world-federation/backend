<?php

namespace Tests\Feature\Api;

use App\Models\ContactMessage;
use App\Models\IntegrityReport;
use App\Models\NewsletterSubscriber;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Empat formulir situs publik yang menulis.
 *
 * Yang dikunci di sini adalah keputusan-keputusan yang tidak kelihatan dari
 * luar: pendaftaran ulang membalas sukses, jebakan bot membalas sukses, laporan
 * integritas tidak menyimpan identitas apa pun, dan turnamen draft tidak bisa
 * dilanggani.
 */
class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Throttle-nya nyata dan diuji di tesnya sendiri; di tes lain ia cuma
        // membuat kegagalan bergantung pada urutan eksekusi.
        RateLimiter::clear('');
    }

    // ------------------------------------------------------------ kontak

    public function test_a_contact_message_is_stored(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'Rina',
            'email' => 'rina@example.org',
            'topic' => 'Tournament support',
            'message' => 'Saya ingin bertanya soal pendaftaran.',
        ])->assertNoContent();

        $message = ContactMessage::query()->sole();

        // Ejaannya DISAMAKAN dengan daftar CMS. Situs publik mengirim sentence
        // case; menyimpannya apa adanya membuat penyaring inbox kehilangan
        // separuh pesannya tanpa satu pun galat.
        $this->assertSame('Tournament Support', $message->topic);
        $this->assertNull($message->read_at);
    }

    public function test_an_unknown_topic_is_refused(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'Rina',
            'email' => 'rina@example.org',
            'topic' => 'Sponsorship deals',
            'message' => 'Saya ingin bertanya soal pendaftaran.',
        ])->assertJsonValidationErrors('topic');
    }

    /** Terisi = bot. Dibalas sukses — galat memberi tahu bot cara lolos. */
    public function test_the_honeypot_swallows_the_submission_without_saying_so(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'Bot',
            'email' => 'bot@example.org',
            'topic' => 'General enquiries',
            'message' => 'Halo halo halo halo.',
            'website' => 'https://spam.example',
        ])->assertNoContent();

        $this->assertDatabaseCount('contact_messages', 0);
    }

    // --------------------------------------------------------- buletin

    public function test_subscribing_twice_is_not_an_error(): void
    {
        $this->postJson('/api/v1/newsletter', ['email' => 'rina@example.org'])->assertNoContent();
        $this->postJson('/api/v1/newsletter', ['email' => 'RINA@example.org'])->assertNoContent();

        // Satu baris, huruf kecil. 422 pada percobaan kedua akan memberi tahu
        // siapa pun apakah sebuah alamat ada di daftar.
        $this->assertSame('rina@example.org', NewsletterSubscriber::query()->sole()->email);
    }

    public function test_subscribing_again_revives_someone_who_left(): void
    {
        NewsletterSubscriber::query()->create([
            'email' => 'rina@example.org',
            'unsubscribed_at' => now()->subMonth(),
        ]);

        $this->postJson('/api/v1/newsletter', ['email' => 'rina@example.org'])->assertNoContent();

        $this->assertTrue(NewsletterSubscriber::query()->sole()->isSubscribed());
    }

    public function test_a_malformed_address_is_refused(): void
    {
        $this->postJson('/api/v1/newsletter', ['email' => 'bukan-email'])
            ->assertJsonValidationErrors('email');
    }

    // ------------------------------------------------------- turnamen

    public function test_someone_can_ask_to_be_notified(): void
    {
        $tournament = Tournament::factory()->create(['status' => 'published', 'published_at' => now()->subDay()]);

        $this->postJson("/api/v1/tournaments/{$tournament->id}/subscribe", ['email' => 'rina@example.org'])
            ->assertNoContent();
        $this->postJson("/api/v1/tournaments/{$tournament->id}/subscribe", ['email' => 'rina@example.org'])
            ->assertNoContent();

        $this->assertSame(1, TournamentNotification::query()->count());
    }

    /**
     * Turnamen yang belum tayang tidak punya halaman, jadi tidak bisa
     * dilanggani — dan id-nya tetap bisa ditebak, jadi route model binding saja
     * tidak cukup.
     */
    public function test_a_draft_tournament_cannot_be_subscribed_to(): void
    {
        $tournament = Tournament::factory()->create(['status' => 'draft', 'published_at' => null]);

        $this->postJson("/api/v1/tournaments/{$tournament->id}/subscribe", ['email' => 'rina@example.org'])
            ->assertNotFound();

        $this->assertDatabaseCount('tournament_notifications', 0);
    }

    // ------------------------------------------------------ integritas

    public function test_an_integrity_report_is_stored(): void
    {
        $this->postJson('/api/v1/integrity-reports', [
            'type' => 'Match manipulation',
            'description' => 'Dua pemain sepakat mengatur hasil babak ketiga.',
        ])->assertNoContent();

        $this->assertSame('Match manipulation', IntegrityReport::query()->sole()->type);
    }

    public function test_a_report_shorter_than_the_form_allows_is_refused(): void
    {
        $this->postJson('/api/v1/integrity-reports', [
            'type' => 'Doping',
            'description' => 'terlalu pendek',
        ])->assertJsonValidationErrors('description');
    }

    /**
     * Saluran ini ANONIM, dan itu harus terlihat dari bentuk tabelnya — bukan
     * dari kesopanan kode yang membacanya. Halaman yang mengirimnya berjanji
     * kerahasiaan; kolom identitas apa pun membuat janji itu bergantung pada
     * siapa yang kebetulan punya akses database.
     */
    public function test_a_report_carries_no_identity_at_all(): void
    {
        $this->postJson('/api/v1/integrity-reports', [
            'type' => 'Doping',
            'description' => 'Sampel diganti sebelum pengujian dilakukan.',
        ]);

        $columns = array_keys(IntegrityReport::query()->sole()->getAttributes());

        foreach (['ip_address', 'ip', 'email', 'name', 'user_agent', 'reporter'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    // --------------------------------------------------------- throttle

    public function test_a_flood_of_submissions_is_refused(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/newsletter', ['email' => "orang{$i}@example.org"])->assertNoContent();
        }

        $this->postJson('/api/v1/newsletter', ['email' => 'keenam@example.org'])
            ->assertStatus(429);
    }
}
