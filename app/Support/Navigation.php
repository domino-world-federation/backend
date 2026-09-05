<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\IntegrityReport;
use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * Struktur sidebar backoffice — satu-satunya sumbernya.
 *
 * Disalin dari komponen Figma `Cwd12fOcsUXmyLF4q1yWVP` node `252:3403`
 * ("Landing Page Sidebar"). Label ditulis persis seperti di desain.
 *
 * Ditaruh di PHP, bukan di dalam `.vue`, karena tiga pemakainya membutuhkan
 * daftar yang sama: sidebar menggambarnya, `PlaceholderController` mengambil
 * judul dan breadcrumb dari sini, dan `routes/web.php` mendaftarkan route dari
 * sini. Menyalinnya ke dua tempat berarti suatu saat item sidebar yang
 * menghasilkan 404.
 *
 * Nama ikon adalah nama komponen Phosphor (`@phosphor-icons/vue`) — lihat
 * catatan substitusi ikon di docs/DESIGN-TOKENS.md §6.
 */
final class Navigation
{
    /**
     * @return array<int, array{
     *     type: 'item'|'group'|'heading',
     *     label: string,
     *     icon?: string,
     *     href?: string,
     *     key?: string,
     *     built?: bool,
     *     children?: array<int, array{label: string, href: string, key: string, built: bool}>
     * }>
     */
    public static function tree(): array
    {
        return [
            self::item('Dashboard', 'SquaresFour', 'dashboard', built: true),

            self::heading('Content Management'),

            /*
             * Menggantikan grup "Landing Page" berisi delapan submenu di
             * komponen master `252:3403`, atas keputusan pemilik repo
             * 2026-09-03.
             *
             * Kedelapannya placeholder, dan saat dicocokkan dengan halaman
             * depan yang sebenarnya: lima akan jadi pintu KEDUA ke modul yang
             * datanya memang tinggal di sana (Stats & Metrics → Federations,
             * Featured Event → Tournaments, News Section → News, Federation
             * Strip → Partners & Heritage), "Overview" menduplikasi widget
             * kelengkapan di Dashboard, dan "About / Mission" menunjuk section
             * yang tidak ada di halaman itu.
             *
             * Yang benar-benar yatim cuma dua — naskah hero dan band ajakan
             * penutup — dan keduanya muat di satu layar. Menu yang berujung
             * halaman kosong mengajari orang bahwa sebagian sidebar memang
             * tidak berfungsi, dan sesudah itu yang berfungsi pun ikut tidak
             * dicoba.
             */
            self::item('Home Page', 'SquaresFour', 'home-page', built: true, permission: 'home.view'),
            self::item('Events & Tournaments', 'Chat', 'tournaments', built: true, permission: 'tournaments.view'),
            // Menu terpisah yang diminta desain Add Tournament sendiri:
            // "Results & Winners … managed from a separate menu after the
            // tournament is completed" (`596:11483`).
            self::item('Results & Winners', 'Trophy', 'results', built: true, permission: 'results.view'),
            self::item('News Articles', 'CalendarBlank', 'news', built: true, permission: 'news.view'),
            // Ini "Resources & Documents" di komponen master `252:3403`, dan
            // ia sudah dibangun: layar `369:5236` menamai dirinya "Document
            // List". Placeholder-nya dihapus alih-alih dibiarkan berdampingan
            // dengan menu Documents di bawah — dua pintu untuk satu modul, satu
            // di antaranya 404.
            self::item('Documents', 'Folder', 'documents', built: true, permission: 'documents.view'),
            self::item('Federations & Members', 'File', 'federations', built: true, permission: 'federations.view'),
            self::item('People & Governance', 'UsersThree', 'people', built: true, permission: 'people.view'),
            /*
             * "Heritage", bukan "Partners & Heritage" — atas keputusan pemilik
             * repo 2026-09-03: deret logo partner dijadikan STATIS di situs
             * publik untuk sekarang, jadi tidak ada yang perlu diinput di sini.
             *
             * Layar Partners-nya TIDAK dihapus, cuma tidak lagi ditaut:
             * `/blocks` tetap bekerja kalau URL-nya dibuka, tabelnya tetap
             * berisi, dan endpoint `/api/v1/partners` tetap hidup. Mengembalikan
             * menunya cukup mengganti baris ini — sementara menghapus layar dan
             * tabelnya berarti membangun ulang saat partner benar-benar mulai
             * berganti.
             *
             * Kuncinya `blocks.heritage`, jadi `href`-nya `/blocks/heritage` —
             * layar yang MASIH dikelola dari sini.
             */
            self::item('Heritage', 'Stack', 'blocks.heritage', built: true, permission: 'blocks.view'),

            // ---------------------------------------------------------------
            // Grup di bawah ini TIDAK ada di komponen sidebar `252:3403`, tapi
            // layarnya ada di kanvas Backoffice dan breadcrumb-nya menuntut
            // tujuan ini: "FAQ › FAQ List › Add FAQ" (`339:4475`) dan
            // "Gallery › List" (`382:5349`). Ditambahkan supaya layar itu punya pintu
            // masuk; penyimpangannya dicatat di docs/PROGRESS.md.
            // ---------------------------------------------------------------
            self::item('FAQ', 'Question', 'faq', built: true, permission: 'faq.view'),
            self::item('Gallery', 'Images', 'gallery', built: true, permission: 'gallery.view'),

            self::heading('Site Settings'),

            /*
             * "Header & Navigation" dan "Footer" DIBUANG dari komponen master
             * `252:3403`, atas keputusan pemilik repo 2026-09-03.
             *
             * Keduanya placeholder yang berujung halaman kosong. Yang mereka
             * kelola juga sudah punya rumahnya: struktur menu dan kolom tautan
             * footer adalah RUTE situs itu sendiri — menyuntingnya lewat CMS
             * membuka jalan menunjuk halaman yang tidak ada — sementara alamat,
             * surel, dan tautan sosial di kaki halaman sudah dikelola
             * "Contact & Social" dan disajikan `/api/v1/settings`.
             *
             * Menu yang berujung halaman kosong mengajari orang bahwa sebagian
             * sidebar memang tidak berfungsi, dan sesudah itu yang berfungsi
             * pun ikut tidak dicoba.
             */
            self::item('SEO & Social', 'Stack', 'seo-social', built: true, permission: 'settings.view'),

            // Sama seperti di atas: breadcrumb `258:8211` dan `258:8095`
            // menyebut "Site Settings › Contact & Social" dan
            // "Site Settings › Legal Pages".
            self::item('Contact & Social', 'AddressBook', 'contact-social', built: true, permission: 'settings.view'),
            self::item('Contact Messages', 'Envelope', 'contact-messages', built: true, permission: 'contact-messages.view'),
            self::item('Newsletter', 'PaperPlaneTilt', 'newsletter', built: true, permission: 'newsletter.view'),

            // Duduk di sini, bukan di Administration: ia kotak masuk dari situs
            // publik seperti dua menu di atasnya, dan yang membukanya orang
            // yang sama. Isinya anonim — lihat `IntegrityReportController`.
            self::item('Integrity Reports', 'ShieldWarning', 'integrity-reports', built: true, permission: 'integrity-reports.view'),
            self::item('Legal Pages', 'Scroll', 'legal-pages', built: true, permission: 'legal-pages.view'),

            self::heading('Administration'),
            self::item('User Management', 'UsersThree', 'users', built: true, permission: 'users.view'),
            self::item('Roles & Permissions', 'ShieldCheck', 'roles', built: true, permission: 'users.view'),

            // Layar `527:7038` menamai dirinya "User Management / IP Whitelist"
            // dan breadcrumb-nya "User Management > IP Whitelist", jadi ia
            // duduk di grup ini. Izinnya sendiri (`ip-whitelist.view`),
            // BUKAN `users.view`: yang boleh mengelola akun tidak otomatis
            // boleh menentukan dari alamat mana orang bisa masuk.
            self::item('IP Whitelist', 'ShieldStar', 'ip-whitelist', built: true, permission: 'ip-whitelist.view'),
            self::item('Activity Log', 'ClockCounterClockwise', 'activity-log', built: true, permission: 'activity-log.view'),

            // Alat kerja, bukan layar produk — tidak ada di wireframe. Ditaruh
            // paling bawah di grupnya sendiri supaya tidak tertukar dengan
            // menu yang benar-benar dipakai admin.
            self::heading('Developer'),
            self::item('Design System', 'Palette', 'design-system', built: true),
        ];
    }

    /**
     * Seluruh tujuan yang bisa dinavigasi, rata — dipakai `routes/web.php`
     * untuk mendaftarkan placeholder dan oleh tes untuk memastikan tidak ada
     * item sidebar yang menghasilkan 404.
     *
     * @return array<string, array{label: string, breadcrumbs: array<int, string>, built: bool}>
     */
    public static function destinations(): array
    {
        $flat = [];

        foreach (self::tree() as $node) {
            if ($node['type'] === 'heading') {
                continue;
            }

            if ($node['type'] === 'item') {
                $flat[$node['key']] = [
                    'label' => $node['label'],
                    'breadcrumbs' => [$node['label']],
                    'built' => $node['built'],
                ];

                continue;
            }

            foreach ($node['children'] as $child) {
                $flat[$child['key']] = [
                    'label' => $child['label'],
                    'breadcrumbs' => [$node['label'], $child['label']],
                    'built' => $child['built'],
                ];
            }
        }

        return $flat;
    }

    /**
     * Pohon navigasi yang sudah disaring untuk satu pengguna.
     *
     * Menu yang tidak boleh ia buka DIBUANG, bukan dinonaktifkan: tombol yang
     * terlihat tapi berujung 403 hanya memberi tahu orang tentang keberadaan
     * modul yang bukan urusannya, dan membuat mereka mengira ada yang rusak.
     *
     * Judul grup yang jadi kosong ikut dibuang — kalau tidak, "Administration"
     * tetap tercetak di atas ruang hampa.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?Authorizable $user): array
    {
        if ($user === null) {
            return [];
        }

        $visible = array_values(array_filter(
            self::tree(),
            fn (array $node) => ($node['permission'] ?? null) === null || $user->can($node['permission']),
        ));

        $out = [];

        foreach ($visible as $index => $node) {
            if ($node['type'] !== 'heading') {
                $badge = self::badgeFor($node['key'] ?? '');

                $out[] = $badge === null ? $node : [...$node, 'badge' => $badge];

                continue;
            }

            $next = $visible[$index + 1] ?? null;

            if ($next !== null && $next['type'] !== 'heading') {
                $out[] = $node;
            }
        }

        return $out;
    }

    /**
     * Angka di sebelah kanan menu — yang BELUM DIBACA di modul itu.
     *
     * Dihitung dari tabel modulnya sendiri (`read_at`), bukan dari tabel
     * `notifications`. Keduanya menjawab pertanyaan yang berbeda: lonceng
     * menghitung yang belum DILIHAT oleh satu orang, badge menghitung yang
     * belum DIKERJAKAN oleh siapa pun. Menandai lonceng terbaca tidak boleh
     * membuat sepuluh pesan yang belum dibalas menghilang dari sidebar.
     *
     * Cuma dua modul yang punya keadaan itu. Buletin tidak: sebuah alamat tidak
     * "belum dibaca", jadi angka di sana akan jadi hiasan yang tidak pernah
     * berkurang.
     *
     * Nol mengembalikan `null`, bukan 0 — badge bertuliskan "0" adalah lencana
     * yang menarik mata untuk memberi tahu bahwa tidak ada apa-apa.
     */
    private static function badgeFor(string $key): ?int
    {
        $count = match ($key) {
            'contact-messages' => ContactMessage::query()->unread()->count(),
            'integrity-reports' => IntegrityReport::query()->unread()->count(),
            default => 0,
        };

        return $count > 0 ? $count : null;
    }

    /** Tujuan yang modulnya belum dibangun — dipakai dashboard. */
    public static function pending(): array
    {
        return array_filter(self::destinations(), fn (array $d) => ! $d['built']);
    }

    private static function item(
        string $label,
        string $icon,
        string $key,
        bool $built = false,
        ?string $permission = null,
    ): array {
        return [
            'type' => 'item',
            'label' => $label,
            'icon' => $icon,
            'key' => $key,
            'href' => self::href($key),
            'built' => $built,
            'permission' => $permission,
        ];
    }

    /** @param array<int, array{0: string, 1: string}> $children */
    private static function group(string $label, string $icon, string $key, array $children): array
    {
        return [
            'type' => 'group',
            'label' => $label,
            'icon' => $icon,
            'key' => $key,
            'children' => array_map(fn (array $c) => [
                'label' => $c[0],
                'key' => "{$key}.{$c[1]}",
                'href' => self::href("{$key}.{$c[1]}"),
                'built' => false,
            ], $children),
        ];
    }

    private static function heading(string $label): array
    {
        return ['type' => 'heading', 'label' => $label];
    }

    /** `landing-page.hero` -> `/landing-page/hero` */
    private static function href(string $key): string
    {
        return '/'.str_replace('.', '/', $key);
    }
}
