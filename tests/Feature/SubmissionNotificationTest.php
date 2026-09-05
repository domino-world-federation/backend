<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use App\Models\SiteSetting;
use App\Models\User;
use App\Notifications\NewContactMessage;
use App\Notifications\NewIntegrityReport;
use App\Notifications\NewsletterDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Siapa diberi tahu tentang apa, saat formulir situs publik masuk.
 *
 * Yang dikunci di sini bukan "surelnya terkirim" — itu urusan SMTP. Yang
 * dikunci adalah keputusan-keputusan yang mudah rusak tanpa ketahuan: laporan
 * integritas tidak boleh membawa isinya keluar sistem, buletin tidak boleh
 * memberi tahu per pendaftar, dan yang tidak boleh membuka sebuah modul tidak
 * boleh menerima kabar tentang isinya.
 */
class SubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('');
        SiteSetting::putMany(['form_recipient_email' => 'inbox@dwf.test'], SiteSetting::GROUP_CONTACT);
    }

    private function contactPayload(): array
    {
        return [
            'name' => 'Rina',
            'email' => 'rina@example.org',
            'topic' => 'General Enquiries',
            'message' => 'Pesan yang cukup panjang untuk lolos validasi.',
        ];
    }

    // ------------------------------------------------------------ kontak

    public function test_a_contact_message_reaches_the_shared_inbox_and_the_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/contact', $this->contactPayload())->assertNoContent();

        Notification::assertSentTo($admin, NewContactMessage::class);
        Notification::assertSentTo(
            new AnonymousNotifiable,
            NewContactMessage::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'inbox@dwf.test',
        );
    }

    /**
     * Lonceng hanya untuk yang punya akun.
     *
     * Kotak masuk bersama cuma sebuah alamat surel — tidak ada baris yang bisa
     * ditandai terbaca untuknya.
     */
    public function test_the_shared_inbox_gets_mail_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $message = ContactMessage::factory()->create();

        $notification = new NewContactMessage($message);

        $this->assertSame(['mail', 'database'], $notification->via($admin));
        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable));
    }

    // -------------------------------------------------------- integritas

    /**
     * **Laporan integritas tidak membawa isinya keluar sistem.**
     *
     * Bukan soal kerapian: halamannya menjanjikan kerahasiaan, dan surel
     * mendarat di kotak masuk pihak ketiga, tersalin ke ponsel, dan terbaca di
     * layar yang terbuka. Tes ini membaca isi pemberitahuannya dan menuntut
     * naskah laporannya TIDAK ada di sana.
     */
    public function test_an_integrity_notification_carries_no_word_of_the_report(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();
        $secret = 'Rahasia yang tidak boleh keluar lewat surel sama sekali.';

        $this->postJson('/api/v1/integrity-reports', [
            'type' => 'Doping',
            'description' => $secret,
        ])->assertNoContent();

        Notification::assertSentTo($admin, NewIntegrityReport::class, function ($notification) use ($admin, $secret) {
            $bell = json_encode($notification->toArray($admin));
            $mail = json_encode($notification->toMail($admin)->toArray());

            $this->assertStringNotContainsString($secret, $bell);
            $this->assertStringNotContainsString($secret, $mail);
            // Jenis insidennya pun tidak — ia sudah cukup untuk menebak isinya.
            $this->assertStringNotContainsString('Doping', $bell);
            $this->assertStringNotContainsString('Doping', $mail);

            return true;
        });
    }

    /**
     * Yang tidak boleh membuka modulnya tidak diberi tahu tentang isinya.
     *
     * `editor` tidak punya `integrity-reports.view`, jadi kabar bahwa ada
     * laporan masuk pun bukan urusannya.
     */
    public function test_someone_without_the_permission_is_not_told(): void
    {
        Notification::fake();

        $editor = User::factory()->withRole('editor')->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/integrity-reports', [
            'type' => 'Doping',
            'description' => 'Laporan yang cukup panjang untuk lolos validasi.',
        ])->assertNoContent();

        Notification::assertSentTo($superAdmin, NewIntegrityReport::class);
        Notification::assertNotSentTo($editor, NewIntegrityReport::class);
    }

    /** Akun yang dimatikan tidak bisa masuk, jadi loncengnya tidak akan pernah dibuka. */
    public function test_a_deactivated_admin_is_not_told(): void
    {
        Notification::fake();

        $inactive = User::factory()->superAdmin()->create(['is_active' => false]);

        $this->postJson('/api/v1/contact', $this->contactPayload())->assertNoContent();

        Notification::assertNotSentTo($inactive, NewContactMessage::class);
    }

    // ---------------------------------------------------------- buletin

    /** Berlangganan buletin TIDAK memberi tahu siapa pun saat itu juga. */
    public function test_subscribing_notifies_nobody_immediately(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create();

        $this->postJson('/api/v1/newsletter', ['email' => 'orang@example.org'])->assertNoContent();

        Notification::assertNothingSent();
    }

    public function test_the_digest_says_nothing_when_nobody_subscribed(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create();

        $this->artisan('dwf:newsletter-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_digest_reports_the_day_when_there_is_something_to_report(): void
    {
        Notification::fake();

        $admin = User::factory()->superAdmin()->create();
        NewsletterSubscriber::factory()->count(3)->create();

        $this->artisan('dwf:newsletter-digest')->assertSuccessful();

        Notification::assertSentTo($admin, NewsletterDigest::class,
            fn (NewsletterDigest $n) => $n->newSubscribers === 3);
    }

    /** Yang sudah berhenti tidak dihitung sebagai pertambahan. */
    public function test_the_digest_counts_the_list_growing_not_the_button_being_pressed(): void
    {
        Notification::fake();

        User::factory()->superAdmin()->create();
        NewsletterSubscriber::factory()->create(['unsubscribed_at' => now()]);

        $this->artisan('dwf:newsletter-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------- log

    /**
     * Pemberitahuan yang gagal tidak boleh menggagalkan formulirnya.
     *
     * Barisnya sudah tersimpan sebelum pemberitahuan disusun; 500 kepada orang
     * yang pesannya SUDAH masuk membuat mereka mengirim ulang pesan yang
     * sebenarnya sampai.
     */
    public function test_a_broken_notifier_still_leaves_the_submission_saved(): void
    {
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('SMTP mati'));

        $this->postJson('/api/v1/contact', $this->contactPayload())->assertNoContent();

        $this->assertDatabaseHas('contact_messages', ['email' => 'rina@example.org']);
    }
}
