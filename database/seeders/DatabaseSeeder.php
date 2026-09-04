<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\Document;
use App\Models\GalleryEvent;
use App\Models\IpWhitelistRule;
use App\Models\LegalPage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Access;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(AccessSeeder::class);

        $user = $this->admin();

        $this->documents();
        $this->gallery();
        $this->legalPages();
        $this->settings();
        $this->messages();
        $this->ipWhitelist($user);

        // Isi yang sama dengan mock situs publik — dijalankan TERAKHIR supaya
        // ia bisa menimpa angka contoh di atas dengan yang benar-benar dipakai
        // halaman itu.
        $this->call(FrontendContentSeeder::class);
    }

    /**
     * Akun admin pertama, dari `.env`.
     *
     * Password tidak pernah ditulis di dalam kode — seeder yang menanam
     * kredensial literal berarti tiap deploy yang menjalankannya membuka pintu
     * yang sama di semua lingkungan.
     */
    private function admin(): User
    {
        $email = config('dwf.admin.email');
        $password = config('dwf.admin.password');

        if (blank($email) || blank($password)) {
            throw new RuntimeException(
                'Isi DWF_ADMIN_EMAIL dan DWF_ADMIN_PASSWORD di .env sebelum menjalankan seeder.',
            );
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => config('dwf.admin.name') ?: 'DWF Admin', 'password' => $password],
        );

        // Akun pertama selalu super admin. Tanpa baris ini, memasang peran ke
        // sistem yang sudah jalan akan mengunci satu-satunya admin di luar
        // seluruh modul — ia punya sesi, tapi tidak punya izin apa pun.
        $user->syncRoles([Access::SUPER_ADMIN]);

        return $user;
    }

    private function documents(): void
    {
        if (Document::query()->exists()) {
            return;
        }

        $rows = [
            ['DWF Annual Report 2025', 'Annual Report', 2_411_724],
            ['International Tournament Standards v3', 'Regulation', 862_133],
            ['Tournament Organiser Toolkit', 'Tournament Toolkit', 5_204_992],
        ];

        foreach ($rows as [$title, $category, $size]) {
            Document::query()->create([
                'title' => $title,
                'slug' => Str::slug($title),
                // Berkasnya sendiri tidak ikut di-seed: unggahan asli yang
                // mengisinya. Path-nya menunjuk berkas yang belum ada, dan
                // tautannya memang akan 404 sampai dokumennya diunggah.
                'file_path' => 'documents/'.Str::slug($title).'.pdf',
                'file_size' => $size,
                'category' => $category,
                // Sejak `369:5236` modul ini memakai Visibility empat keadaan,
                // bukan sakelar `is_active` dua keadaan.
                'status' => Document::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);
        }
    }

    private function gallery(): void
    {
        foreach ([['Madrid Qualifier 2026', 'tournament'], ['DWF Community Day', 'event']] as $index => [$name, $type]) {
            GalleryEvent::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => $type, 'held_on' => now()->subMonths($index + 1)->toDateString()],
            );
        }
    }

    private function legalPages(): void
    {
        foreach ([['privacy-policy', 'Privacy Policy'], ['terms', 'Terms & Conditions']] as [$key, $title]) {
            $page = LegalPage::query()->updateOrCreate(
                ['key' => $key],
                ['title' => $title, 'slug' => $key, 'last_updated_at' => now()->toDateString()],
            );

            if ($page->blocks()->doesntExist()) {
                $page->blocks()->create([
                    'title' => $key === 'terms' ? 'Acceptance of Terms' : 'Information We Collect',
                    'description' => '<p>Isi contoh untuk pengembangan lokal. Ganti sebelum tayang.</p>',
                    'is_active' => true,
                    'position' => 1,
                ]);
            }
        }
    }

    private function settings(): void
    {
        SiteSetting::putMany([
            'primary_email' => 'contact@dwf-domino.org',
            'footer_address_label' => 'Headquarters, Lausanne, CH',
            'headquarters_address' => 'Maison du Sport International, Lausanne, Switzerland',
            'form_recipient_email' => 'contact@dwf-domino.org',
            'social_instagram' => 'dominoworldfederation',
            'social_tiktok' => 'dwf.domino',
            'social_x' => 'dwf_domino',
            'social_facebook' => 'dominoworldfederation',
            'social_youtube' => 'dwfdomino',
        ], SiteSetting::GROUP_CONTACT);
    }

    private function messages(): void
    {
        if (ContactMessage::query()->exists()) {
            return;
        }

        $rows = [
            ['Kenji Mori', 'kenji.mori@example.com', 'Japan', 'Media Requests', 'Interview request for DWF', 0, false],
            ['Laura Diaz', 'laura.diaz@example.com', 'Spain', 'General Enquiries', 'Official rulebook clarification', 1, false],
            ['David Okoro', 'd.okoro@example.com', 'Nigeria', 'Partnerships', 'Regional event partnership', 1, true],
            ['Sofia Chen', 'sofia.chen@example.com', 'Singapore', 'Membership Information', 'Federation affiliation process', 3, true],
        ];

        foreach ($rows as [$name, $email, $country, $topic, $subject, $daysAgo, $read]) {
            ContactMessage::query()->create([
                'name' => $name,
                'email' => $email,
                'country' => $country,
                'topic' => $topic,
                'subject' => $subject,
                'message' => "Halo DWF,\n\n{$subject}. Mohon informasinya.\n\nTerima kasih,\n{$name}",
                'read_at' => $read ? now()->subDays($daysAgo) : null,
                'created_at' => now()->subDays($daysAgo),
                'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    /**
     * Contoh aturan daftar IP — baris-baris dari desain `527:7039`.
     *
     * ── Semuanya ditanam NONAKTIF, dan itu bukan kelalaian. ──
     *
     * Aturan aktif yang menyasar "All Admins" akan langsung menegakkan dirinya
     * pada request berikutnya. Seeder yang menanamnya menyala berarti siapa pun
     * yang menjalankan `db:seed` di mesin yang bukan `local` akan terkunci di
     * luar backoffice oleh data contoh — dan satu-satunya layar yang bisa
     * membatalkannya ada di balik pintu yang baru saja ia kunci.
     *
     * Alamatnya juga bukan alamat desain. Desain memakai `103.12.34.56` dan
     * `114.10.22.15`, yang benar-benar dialokasikan ke jaringan orang lain;
     * yang dipakai di sini blok dokumentasi RFC 5737 dan RFC 3849. Data contoh
     * yang menyebut alamat nyata adalah cara termudah membuat seseorang
     * menyalinnya ke produksi.
     *
     * Nama peran juga disesuaikan: desain menulis "Admin PB", "Tournament
     * Admin", dan "Content Admin", sedangkan peran yang ada di sistem ini
     * `admin`, `editor`, dan `viewer`.
     */
    private function ipWhitelist(User $admin): void
    {
        $roles = Role::query()->pluck('id', 'name');

        $rules = [
            ['Head Office', '198.51.100.10', IpWhitelistRule::SCOPE_ALL, null, null, null],
            ['Security VPN', '203.0.113.0/24', IpWhitelistRule::SCOPE_ALL, null, null, null],
            ['PB Office Jakarta', '192.0.2.15', IpWhitelistRule::SCOPE_ROLE, $roles['admin'] ?? null, null, null],
            ['Event Operations', '198.51.100.42', IpWhitelistRule::SCOPE_ROLE, $roles['editor'] ?? null, null, '2026-12-31 23:59'],
            ['Media Agency', '192.0.2.80/29', IpWhitelistRule::SCOPE_ROLE, $roles['viewer'] ?? null, null, '2026-09-30 23:59'],
            ['Support Engineer', '2001:db8::88', IpWhitelistRule::SCOPE_USER, null, $admin->id, '2026-09-05 23:59'],
        ];

        foreach ($rules as [$name, $range, $scope, $roleId, $userId, $expiresAt]) {
            IpWhitelistRule::query()->updateOrCreate(
                ['name' => $name],
                [
                    'ip_range' => $range,
                    'scope' => $scope,
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'validity' => $expiresAt === null
                        ? IpWhitelistRule::VALIDITY_PERMANENT
                        : IpWhitelistRule::VALIDITY_TEMPORARY,
                    'expires_at' => $expiresAt,
                    'notes' => null,
                    'is_active' => false,
                    'created_by_id' => $admin->id,
                    'updated_by_id' => $admin->id,
                ],
            );
        }
    }
}
