<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\IntegrityReport;
use App\Models\User;
use App\Notifications\NewContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Lonceng di topbar dan angka di sidebar.
 *
 * Keduanya menghitung hal yang BERBEDA, dan tes di bawah menjaga bedanya:
 * lonceng menghitung yang belum dilihat oleh satu orang, badge menghitung yang
 * belum dikerjakan oleh siapa pun. Kalau keduanya jadi satu angka, menandai
 * lonceng terbaca akan membuat pesan yang belum dibalas menghilang dari
 * sidebar.
 */
class NotificationBellTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user): void
    {
        $user->notify(new NewContactMessage(ContactMessage::factory()->create()));
    }

    public function test_the_bell_carries_what_is_waiting(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->notify($user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('notifications.unreadCount', 1)
                ->has('notifications.items', 1)
                ->where('notifications.items.0.isRead', false));
    }

    public function test_opening_one_marks_it_read_and_goes_where_it_points(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->notify($user);

        $notification = $user->notifications()->first();
        $target = $notification->data['url'];

        $this->actingAs($user)
            ->get("/notifications/{$notification->id}")
            ->assertRedirect($target);

        $this->assertNotNull($user->fresh()->notifications()->first()->read_at);
    }

    /**
     * Notifikasi orang lain TIDAK DITEMUKAN, bukan ditolak.
     *
     * 403 memberi tahu bahwa id itu ada, dan id notifikasi muncul di URL.
     */
    public function test_someone_elses_notification_does_not_exist(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $stranger = User::factory()->superAdmin()->create();
        $this->notify($owner);

        $id = $owner->notifications()->first()->id;

        $this->actingAs($stranger)->get("/notifications/{$id}")->assertNotFound();
        $this->assertNull($owner->fresh()->notifications()->first()->read_at);
    }

    public function test_marking_all_read_empties_the_count_but_not_the_list(): void
    {
        $user = User::factory()->superAdmin()->create();
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('notifications.unreadCount', 0)
                // Masih terdaftar: panelnya tempat melihat apa yang baru lewat,
                // bukan hanya apa yang belum disentuh.
                ->has('notifications.items', 2));
    }

    /** Halaman login tidak menggambar lonceng, jadi ia tidak dikirimi isinya. */
    public function test_a_signed_out_visitor_gets_nothing(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('notifications', null));
    }

    // ------------------------------------------------------------ badge

    public function test_the_sidebar_counts_what_nobody_has_dealt_with(): void
    {
        $user = User::factory()->superAdmin()->create();

        ContactMessage::factory()->count(3)->create(['read_at' => null]);
        ContactMessage::factory()->create(['read_at' => now()]);
        IntegrityReport::factory()->create(['read_at' => null]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $p) {
                $nav = collect($p->toArray()['props']['navigation']);

                $this->assertSame(3, $nav->firstWhere('key', 'contact-messages')['badge'] ?? null);
                $this->assertSame(1, $nav->firstWhere('key', 'integrity-reports')['badge'] ?? null);
            });
    }

    /** Nol tidak mengirim apa-apa — lencana bertuliskan "0" menarik mata untuk tidak ada apa-apa. */
    public function test_nothing_waiting_means_no_badge_at_all(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $p) {
                $nav = collect($p->toArray()['props']['navigation']);

                $this->assertArrayNotHasKey('badge', (array) $nav->firstWhere('key', 'contact-messages'));
            });
    }

    /**
     * Badge mengikuti izin, karena menunya mengikuti izin.
     *
     * `editor` tidak melihat menu Contact Messages sama sekali — jadi tidak ada
     * tempat bagi angkanya untuk bocor.
     */
    public function test_a_hidden_menu_carries_no_number(): void
    {
        $editor = User::factory()->withRole('editor')->create();
        ContactMessage::factory()->count(2)->create(['read_at' => null]);

        $this->actingAs($editor)
            ->get('/dashboard')
            ->assertInertia(function (AssertableInertia $p) {
                $nav = collect($p->toArray()['props']['navigation']);

                $this->assertNull($nav->firstWhere('key', 'contact-messages'));
                $this->assertNull($nav->firstWhere('key', 'integrity-reports'));
            });
    }
}
