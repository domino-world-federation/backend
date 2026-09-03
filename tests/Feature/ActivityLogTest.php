<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_updating_and_deleting_are_all_recorded(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        $category = NewsCategory::factory()->create(['name' => 'Awal']);
        $category->update(['name' => 'Diubah']);
        $category->delete();

        $events = Activity::where('log_name', 'news-category')->pluck('event')->all();

        $this->assertSame(['created', 'updated', 'deleted'], $events);
    }

    public function test_the_log_records_who_did_it(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $this->actingAs($actor)->post('/news/categories', ['name' => 'Dari HTTP', 'is_active' => true]);

        $entry = Activity::where('log_name', 'news-category')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertTrue($actor->is($entry->causer));
    }

    public function test_an_update_records_both_the_old_and_the_new_value(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = NewsCategory::factory()->create(['name' => 'Sebelum']);
        $category->update(['name' => 'Sesudah']);

        $entry = Activity::where('event', 'updated')->latest('id')->first();

        $this->assertSame('Sesudah', $entry->properties['attributes']['name']);
        $this->assertSame('Sebelum', $entry->properties['old']['name']);
    }

    public function test_saving_without_changing_anything_writes_no_entry(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = NewsCategory::factory()->create();
        $before = Activity::count();

        $category->update(['name' => $category->name]);

        $this->assertSame($before, Activity::count());
    }

    // --- Yang paling penting: rahasia tidak boleh menumpuk di jejak audit ---

    public function test_passwords_never_reach_the_log(): void
    {
        // Log audit yang menyimpan hash sandi lama mengubah dirinya sendiri
        // jadi sasaran: ia menumpuk kredensial dalam bentuk yang lebih mudah
        // dipanen daripada tabel aslinya.
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        $actor->update(['name' => 'Nama Baru', 'password' => 'sandi-yang-baru-sekali']);

        $raw = DB::table('activity_log')->pluck('properties')->implode(' ');

        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('sandi-yang-baru-sekali', $raw);
    }

    public function test_two_factor_secrets_never_reach_the_log(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        $actor->forceFill(['two_factor_secret' => 'RAHASIA-TOTP-XYZ'])->save();

        $raw = DB::table('activity_log')->pluck('properties')->implode(' ');

        $this->assertStringNotContainsString('two_factor_secret', $raw);
        $this->assertStringNotContainsString('RAHASIA-TOTP-XYZ', $raw);
    }

    // --- Pengaturan situs: satu entri, bukan sembilan ----------------------

    public function test_saving_settings_writes_exactly_one_entry(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->put('/contact-social', [
            'primary_email' => 'baru@dwf-domino.org',
            'footer_address_label' => 'Lausanne',
            'headquarters_address' => 'Maison du Sport',
            'form_recipient_email' => 'inbox@dwf-domino.org',
        ]);

        $entries = Activity::where('log_name', 'site-settings')->get();

        $this->assertCount(1, $entries);
        $this->assertSame('baru@dwf-domino.org', $entries->first()->properties['attributes']['primary_email']);
    }

    public function test_saving_identical_settings_writes_nothing(): void
    {
        $actor = User::factory()->superAdmin()->create();

        $payload = [
            'primary_email' => 'sama@dwf-domino.org',
            'footer_address_label' => 'Lausanne',
            'headquarters_address' => 'Maison du Sport',
            'form_recipient_email' => 'sama@dwf-domino.org',
        ];

        SiteSetting::putMany($payload, SiteSetting::GROUP_CONTACT);

        $this->actingAs($actor)->put('/contact-social', $payload);

        $this->assertSame(0, Activity::where('log_name', 'site-settings')->count());
    }

    // --- Layar ------------------------------------------------------------

    public function test_module_names_are_readable_not_slugs(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        NewsCategory::factory()->create();

        $entry = $this->get('/activity-log')->viewData('page')['props']['entries']['data'][0];

        $this->assertSame('News Category', $entry['moduleLabel']);
        // Nilai mentahnya tetap dikirim — itu yang dipakai penyaring.
        $this->assertSame('news-category', $entry['module']);
    }

    public function test_the_record_name_is_not_repeated_as_a_class_name(): void
    {
        // Baris utama sudah mencetak namanya; mengulang "NewsCategory #6" di
        // bawahnya hanya menyebut hal yang sama dua kali.
        $this->actingAs(User::factory()->superAdmin()->create());

        NewsCategory::factory()->create(['name' => 'Tournament']);

        $entry = $this->get('/activity-log')->viewData('page')['props']['entries']['data'][0];

        $this->assertSame('Tournament', $entry['subject']);
        $this->assertArrayNotHasKey('subjectType', $entry);
        $this->assertNotNull($entry['subjectId']);
    }

    public function test_the_viewer_lists_entries_with_a_flattened_diff(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        $category = NewsCategory::factory()->create(['name' => 'Sebelum']);
        $category->update(['name' => 'Sesudah']);

        $this->get('/activity-log')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('ActivityLog/Index')
                ->has('entries.data')
                ->has('entries.data.0.changes'));
    }

    public function test_long_values_are_truncated_in_the_viewer(): void
    {
        // Isi editor bisa ribuan karakter; tabel audit bukan tempat membaca
        // artikel — dan mengirimnya utuh membuat satu halaman log berukuran
        // megabyte.
        $this->actingAs(User::factory()->superAdmin()->create());

        NewsArticle::factory()->create(['body' => '<p>'.str_repeat('panjang sekali ', 200).'</p>']);

        $changes = $this->get('/activity-log')->viewData('page')['props']['entries']['data'][0]['changes'];
        $body = collect($changes)->firstWhere('field', 'body');

        $this->assertNotNull($body);
        // `Str::limit(120)` memotong di 120 lalu menambahkan '…'.
        $this->assertLessThanOrEqual(123, mb_strlen($body['to']));
        // Tag HTML dibuang supaya kolomnya terbaca.
        $this->assertStringNotContainsString('<p>', $body['to']);
    }

    public function test_the_log_can_be_filtered(): void
    {
        $actor = User::factory()->superAdmin()->create();
        $this->actingAs($actor);

        // Artikel dibuat memakai kategori yang SUDAH ada; kalau tidak,
        // factory-nya membuat kategori kedua dan menambah satu entri lagi ke
        // modul yang sedang disaring.
        $category = NewsCategory::factory()->create();
        NewsArticle::factory()->create(['news_category_id' => $category->id]);

        $this->get('/activity-log?module=news-category')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('entries.data', 1));

        $this->get('/activity-log?module=news-article')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('entries.data', 1));
    }

    // --- Otentikasi ---------------------------------------------------------

    public function test_signing_in_and_out_are_both_recorded(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-benar']);
        $this->post('/logout');

        $events = Activity::where('log_name', 'authentication')->pluck('event')->all();

        $this->assertSame(['login', 'logout'], $events);
    }

    public function test_each_auth_event_is_recorded_exactly_once(): void
    {
        // Laravel memindai `app/Listeners` dan mendaftarkan sendiri method
        // bernama `handle*`. Kalau method di `RecordAuthActivity` dinamai
        // begitu, ia terdaftar dua kali dan tiap login menghasilkan dua baris
        // identik — tanpa galat apa pun. Tes ini yang menangkapnya.
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-benar']);

        $this->assertSame(1, Activity::where('event', 'login')->count());
    }

    public function test_a_sign_in_records_who_and_from_where(): void
    {
        // Tanpa IP, entri "admin masuk" tidak bisa dibedakan dari "seseorang
        // dengan sandi admin masuk dari tempat lain".
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-benar']);

        $entry = Activity::where('event', 'login')->first();

        $this->assertTrue($user->is($entry->causer));
        $this->assertTrue($user->is($entry->subject));
        $this->assertNotNull($entry->properties['ip']);
    }

    public function test_a_failed_attempt_is_recorded_without_the_password(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'tebakan-nyaris-benar']);

        $entry = Activity::where('event', 'failed')->first();

        $this->assertNotNull($entry);
        $this->assertSame($user->email, $entry->properties['email']);

        // Tebakan yang nyaris benar jauh lebih berharga bagi penyerang
        // daripada hash — ia tidak boleh menumpuk di jejak audit.
        $raw = DB::table('activity_log')->pluck('properties')->implode(' ');
        $this->assertStringNotContainsString('tebakan-nyaris-benar', $raw);
        $this->assertStringNotContainsString('password', $raw);
    }

    public function test_an_attempt_on_an_unknown_email_still_names_the_email(): void
    {
        $this->post('/login', ['email' => 'hantu@example.com', 'password' => 'apa-saja']);

        $entry = Activity::where('event', 'failed')->first();

        $this->assertNotNull($entry);
        $this->assertNull($entry->causer);
        $this->assertSame('hantu@example.com', $entry->properties['email']);

        // Entri otentikasi tidak punya `attributes`; label "Record" harus tetap
        // menemukan emailnya dari properti datar, bukan jatuh ke `#`.
        // Perlu masuk dulu untuk membuka layar lognya — dan login itu sendiri
        // menambah entri, jadi baris yang dicari dicari berdasarkan event.
        $this->actingAs(User::factory()->superAdmin()->create());

        $rows = $this->get('/activity-log')->viewData('page')['props']['entries']['data'];
        $row = collect($rows)->firstWhere('event', 'failed');

        $this->assertSame('hantu@example.com', $row['subject']);
    }

    public function test_a_lockout_is_recorded(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'salah']);
        }

        $this->assertSame(1, Activity::where('event', 'lockout')->count());

        RateLimiter::clear(strtolower($user->email).'|127.0.0.1');
    }

    public function test_auth_entries_read_as_words_in_the_viewer(): void
    {
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);
        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-benar']);

        $entry = $this->get('/activity-log')->viewData('page')['props']['entries']['data'][0];

        $this->assertSame('Authentication', $entry['moduleLabel']);
        $this->assertSame('Login', $entry['eventLabel']);
        $this->assertSame($user->name, $entry['subject']);
    }

    // --- Asal perubahan -----------------------------------------------------

    public function test_every_entry_carries_the_ip_not_just_auth_ones(): void
    {
        // Catatannya datang dari tiga jalur berbeda — trait model, listener
        // otentikasi, dan controller pengaturan. Menitipkan IP ke tiap
        // pemanggil berarti suatu saat ada jalur yang lupa.
        $user = User::factory()->superAdmin()->create(['password' => 'sandi-yang-benar']);

        $this->post('/login', ['email' => $user->email, 'password' => 'sandi-yang-benar']);
        $this->actingAs($user)->post('/news/categories', ['name' => 'Lewat HTTP', 'is_active' => true]);

        foreach (Activity::all() as $entry) {
            $this->assertNotNull($entry->properties['ip'], "entri {$entry->log_name} tanpa IP");
        }
    }

    public function test_the_viewer_shows_a_readable_device_and_keeps_the_raw_agent(): void
    {
        $user = User::factory()->superAdmin()->create();

        $agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 '
            .'(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

        $this->actingAs($user)
            ->withHeader('User-Agent', $agent)
            ->post('/news/categories', ['name' => 'Dari Chrome', 'is_active' => true]);

        $row = $this->actingAs($user)->get('/activity-log')
            ->viewData('page')['props']['entries']['data'][0];

        $this->assertSame('Chrome · macOS', $row['device']);
        $this->assertNotNull($row['ip']);
        // Ringkasannya bisa salah untuk browser tak dikenal; aslinya harus
        // tetap ada supaya tidak ada informasi yang hilang.
        $this->assertSame($agent, $row['userAgent']);
    }

    public function test_edge_is_not_mistaken_for_chrome(): void
    {
        // Tiap browser menyamar sebagai pendahulunya: Edge memuat "Chrome"
        // DAN "Safari" di user agent-nya.
        $this->assertSame(
            'Edge · Windows',
            UserAgent::summarise(
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
                .'(KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0'
            )['label'],
        );

        $this->assertSame(
            'Safari · iOS',
            UserAgent::summarise(
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 '
                .'Version/17.0 Mobile/15E148 Safari/604.1'
            )['label'],
        );
    }

    public function test_an_unrecognised_agent_shows_its_raw_value_not_unknown(): void
    {
        // "Unknown" menyembunyikan informasi yang sebenarnya ada.
        $this->assertSame(
            'PenjelajahAneh/1.0',
            UserAgent::summarise('PenjelajahAneh/1.0')['label'],
        );

        $this->assertNull(UserAgent::summarise(null)['label']);
    }

    public function test_there_is_no_way_to_delete_log_entries(): void
    {
        // Jejak audit yang bisa dirapikan lewat antarmukanya sendiri berhenti
        // jadi jejak audit.
        $routes = collect(app('router')->getRoutes())
            ->filter(fn ($r) => str_contains($r->uri(), 'activity-log'))
            ->flatMap(fn ($r) => $r->methods())
            ->unique()
            ->values()
            ->all();

        $this->assertSame(['GET', 'HEAD'], $routes);
    }
}
