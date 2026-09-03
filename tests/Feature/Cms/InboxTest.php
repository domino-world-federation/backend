<?php

namespace Tests\Feature\Cms;

use App\Models\IntegrityReport;
use App\Models\NewsletterSubscriber;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Dua kotak masuk yang isinya datang dari situs publik: Buletin dan Laporan
 * Integritas.
 */
class InboxTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->superAdmin()->create();
    }

    // -------------------------------------------------------- buletin

    public function test_the_subscriber_list_renders(): void
    {
        NewsletterSubscriber::factory()->count(2)->create();

        $this->actingAs($this->actor())
            ->get('/newsletter')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Newsletter/Index')
                ->has('subscribers.data', 2)
                ->where('subscribedCount', 2));
    }

    /**
     * Sakelar langganan MENANDAI, bukan menghapus.
     *
     * Baris yang hilang berarti alamat yang sama bisa didaftarkan ulang oleh
     * siapa pun — termasuk oleh orang yang baru saja keluar.
     */
    public function test_unsubscribing_marks_instead_of_deleting(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->actingAs($this->actor())
            ->patch("/newsletter/{$subscriber->id}/status", ['is_subscribed' => false])
            ->assertRedirect();

        $this->assertModelExists($subscriber);
        $this->assertFalse($subscriber->fresh()->isSubscribed());
    }

    public function test_deleting_really_deletes(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->actingAs($this->actor())
            ->delete("/newsletter/{$subscriber->id}")
            ->assertRedirect();

        $this->assertModelMissing($subscriber);
    }

    /** Tidak ada layar tambah: alamat yang diketik admin tidak pernah diminta pemiliknya. */
    public function test_there_is_no_way_to_add_a_subscriber_by_hand(): void
    {
        $this->actingAs($this->actor())->post('/newsletter', ['email' => 'x@example.org'])
            ->assertStatus(405);
    }

    public function test_a_viewer_cannot_change_a_subscription(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->patch("/newsletter/{$subscriber->id}/status", ['is_subscribed' => false])
            ->assertForbidden();
    }

    // ----------------------------------------------------- integritas

    public function test_opening_a_report_marks_it_read(): void
    {
        $report = IntegrityReport::factory()->create();

        $this->actingAs($this->actor())
            ->get("/integrity-reports/{$report->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Integrity/Show'));

        $this->assertNotNull($report->fresh()->read_at);
    }

    /**
     * Layar laporan tidak boleh membocorkan identitas — dan cara paling pasti
     * memastikannya adalah tidak ada yang bisa dibocorkan.
     */
    public function test_the_report_screen_sends_nothing_but_the_report(): void
    {
        $report = IntegrityReport::factory()->create();

        $this->actingAs($this->actor())
            ->get("/integrity-reports/{$report->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('report', fn (AssertableInertia $r) => $r
                    ->has('id')->has('type')->has('description')->has('receivedAt')));
    }

    public function test_reports_can_be_filtered_by_type(): void
    {
        IntegrityReport::factory()->create(['type' => 'Doping']);
        IntegrityReport::factory()->create(['type' => 'Match manipulation']);

        $this->actingAs($this->actor())
            ->get('/integrity-reports?type=Doping')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('reports.data', 1));
    }

    public function test_a_viewer_cannot_delete_a_report(): void
    {
        $report = IntegrityReport::factory()->create();

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->delete("/integrity-reports/{$report->id}")
            ->assertForbidden();
    }

    // ------------------------------------------ notifikasi turnamen

    /**
     * Daftar alamat dijaga `tournaments.update`, bukan `.view`.
     *
     * Mengunduh daftar alamat orang adalah tindakan, bukan pembacaan — dan
     * berkasnya berpindah lewat surel dan folder bersama begitu jadi.
     */
    public function test_downloading_the_notify_list_needs_more_than_read_access(): void
    {
        $tournament = Tournament::factory()->create();

        $this->actingAs(User::factory()->withRole('viewer')->create())
            ->get("/tournaments/{$tournament->id}/notifications/export")
            ->assertForbidden();
    }

    public function test_the_notify_list_carries_the_addresses_and_is_logged(): void
    {
        $tournament = Tournament::factory()->create();
        $tournament->notifications()->create(['email' => 'rina@example.org']);

        $response = $this->actingAs($this->actor())
            ->get("/tournaments/{$tournament->id}/notifications/export")
            ->assertOk();

        $this->assertStringContainsString('rina@example.org', $this->streamed($response));

        // Yang diaudit dari modul ini bukan siapa yang mendaftar, melainkan
        // siapa yang mengunduh daftarnya.
        $this->assertDatabaseHas('activity_log', ['log_name' => 'tournament', 'event' => 'exported']);
    }

    private function streamed(TestResponse $response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return (string) ob_get_clean();
    }
}
