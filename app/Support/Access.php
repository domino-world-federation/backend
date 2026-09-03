<?php

namespace App\Support;

/**
 * Daftar peran dan izin — satu tempat.
 *
 * Izin dibangkitkan dari `MODULES × ACTIONS`, bukan ditulis satu per satu:
 * daftar yang diketik tangan akan berbeda dari yang diperiksa `can()` begitu
 * ada modul baru, dan yang terjadi bukan galat melainkan tombol yang diam-diam
 * hilang untuk semua orang.
 */
final class Access
{
    /** kunci modul => label yang dibaca manusia di layar peran */
    public const MODULES = [
        'home' => 'Home Page',
        'news' => 'News',
        'tournaments' => 'Events & Tournaments',
        'federations' => 'Federations & Members',
        'results' => 'Results & Winners',
        'people' => 'People & Governance',
        'blocks' => 'Partners & Heritage',
        'faq' => 'FAQ',
        'documents' => 'Documents',
        'gallery' => 'Gallery',
        'legal-pages' => 'Legal Pages',
        'settings' => 'Site Settings',
        'contact-messages' => 'Contact Messages',
        'newsletter' => 'Newsletter',
        'integrity-reports' => 'Integrity Reports',
        'users' => 'User Management',
        'ip-whitelist' => 'IP Whitelist',
        'activity-log' => 'Activity Log',
    ];

    public const ACTIONS = ['view', 'create', 'update', 'delete'];

    /**
     * Modul yang hanya punya dua aksi.
     *
     * Pengaturan situs tidak pernah "dibuat" atau "dihapus" — ia sudah ada dan
     * hanya diubah. Log aktivitas hanya dibaca; menghapus jejak audit lewat UI
     * justru menghapus gunanya. Memberi izin yang tidak pernah bisa dipakai
     * membuat layar peran penuh kotak centang yang tidak berarti apa-apa.
     */
    private const LIMITED = [
        'home' => ['view', 'update'],
        'settings' => ['view', 'update'],
        'activity-log' => ['view'],
        'contact-messages' => ['view', 'delete'],
        // Barisnya lahir dari formulir situs publik, bukan diketik admin —
        // "buat" dan "ubah" tidak pernah bisa dipakai.
        'newsletter' => ['view', 'update', 'delete'],
        'integrity-reports' => ['view', 'delete'],
    ];

    public const SUPER_ADMIN = 'super-admin';

    /**
     * Keterangan peran bawaan untuk layar `528:9745`.
     *
     * `summary` adalah kolom "Permission Summary" — kalimat yang ditulis orang,
     * BUKAN daftar izin yang dirangkai otomatis. Desain menulis "Players, KYC,
     * federation content" untuk peran berizin belasan, jadi yang dicari
     * pembacanya adalah niat perannya, bukan cerminan datanya.
     *
     * `scope` semuanya `global` untuk sekarang: tidak ada peran bawaan yang
     * terikat satu federasi. Peran berlingkup federasi dibuat lewat layarnya.
     *
     * @var array<string, array{scope: string, summary: string}>
     */
    public const ROLE_META = [
        self::SUPER_ADMIN => ['scope' => 'global', 'summary' => 'Full Backoffice access'],
        'admin' => ['scope' => 'global', 'summary' => 'All modules except admin accounts and IP whitelist'],
        'editor' => ['scope' => 'global', 'summary' => 'News, FAQ, documents, gallery, tournaments, federations'],
        'viewer' => ['scope' => 'global', 'summary' => 'Read-only operational access'],
    ];

    /** @return array<int, string> */
    public static function permissions(): array
    {
        $out = [];

        foreach (array_keys(self::MODULES) as $module) {
            foreach (self::LIMITED[$module] ?? self::ACTIONS as $action) {
                $out[] = "{$module}.{$action}";
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public static function actionsFor(string $module): array
    {
        return self::LIMITED[$module] ?? self::ACTIONS;
    }

    /**
     * Peran bawaan beserta izinnya.
     *
     * `super-admin` sengaja TIDAK didaftar izinnya di sini — ia dilewatkan
     * lewat `Gate::before` (lihat `AuthServiceProvider`), jadi modul baru
     * otomatis terjangkau tanpa harus ingat memperbarui daftar ini.
     *
     * @return array<string, array<int, string>|null>
     */
    public static function roles(): array
    {
        $editorModules = ['news', 'faq', 'documents', 'gallery', 'tournaments', 'federations', 'results', 'people', 'blocks'];

        return [
            self::SUPER_ADMIN => null,

            // `ip-whitelist` ikut dikecualikan bersama `users`, bukan karena
            // ia bagian dari modul itu, melainkan karena keduanya menentukan
            // SIAPA yang bisa masuk. Desain menuliskannya: "IP Whitelist
            // changes should be restricted to Super Admin" (`527:7870`).
            // Peran yang boleh mengubah daftar IP bisa memberi dirinya jalan
            // masuk dari mana saja, dan itu membuat sisa izinnya tidak berarti.
            'admin' => collect(self::permissions())
                ->reject(fn (string $p) => str_starts_with($p, 'users.'))
                ->reject(fn (string $p) => str_starts_with($p, 'ip-whitelist.'))
                ->values()
                ->all(),

            'editor' => collect(self::permissions())
                ->filter(fn (string $p) => in_array(explode('.', $p)[0], $editorModules, true))
                ->values()
                ->all(),

            'viewer' => collect(self::permissions())
                ->filter(fn (string $p) => str_ends_with($p, '.view'))
                ->reject(fn (string $p) => str_starts_with($p, 'users.'))
                ->reject(fn (string $p) => str_starts_with($p, 'ip-whitelist.'))
                ->values()
                ->all(),
        ];
    }
}
