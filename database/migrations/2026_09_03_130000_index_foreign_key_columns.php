<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk setiap kolom kunci asing.
 *
 * **PostgreSQL tidak membuatnya sendiri.** MySQL membuat indeks otomatis untuk
 * tiap `FOREIGN KEY`; Postgres hanya membuatnya untuk kolom yang DITUNJUK
 * (primary key sisi seberang), tidak untuk kolom yang menunjuk. Jadi setiap
 * `foreignId()->constrained()` di repo ini menghasilkan kolom tanpa indeks —
 * 35 di antaranya saat migrasi ini ditulis.
 *
 * Dua akibatnya, dan keduanya diam:
 *
 *   1. Tiap `with('editor')`, `withCount('notifications')`, dan penyaring
 *      `where('faq_category_id', ...)` memindai seluruh tabel. Tidak terasa di
 *      tiga baris contoh; terasa di tiga ribu.
 *   2. `ON DELETE CASCADE` harus memindai tabel anak untuk MENEMUKAN yang
 *      harus dihapus. Menghapus satu turnamen memindai `tournament_officials`,
 *      `tournament_schedule_entries`, dan `tournament_notifications` utuh.
 *
 * Sengaja satu migrasi berisi daftar, bukan satu per tabel: yang diperbaiki
 * adalah kelalaian yang sistematis, dan daftar di satu tempat membuat yang
 * berikutnya kelihatan hilang.
 */
return new class extends Migration
{
    /** @var array<int, array{0: string, 1: string}> */
    private const COLUMNS = [
        ['admin_invitations', 'created_by_id'],
        ['board_members', 'updated_by_id'],
        ['champions', 'updated_by_id'],
        ['document_tournament', 'document_id'],
        ['documents', 'created_by_id'],
        ['documents', 'published_by_id'],
        ['documents', 'updated_by_id'],
        ['faqs', 'faq_category_id'],
        ['faqs', 'updated_by_id'],
        ['federation_stats', 'updated_by_id'],
        ['gallery_items', 'created_by_id'],
        ['gallery_items', 'gallery_event_id'],
        ['gallery_items', 'published_by_id'],
        ['gallery_items', 'updated_by_id'],
        ['heritage_milestones', 'updated_by_id'],
        ['ip_whitelist_rules', 'created_by_id'],
        ['ip_whitelist_rules', 'role_id'],
        ['ip_whitelist_rules', 'updated_by_id'],
        ['ip_whitelist_rules', 'user_id'],
        ['legal_pages', 'updated_by_id'],
        ['member_federations', 'updated_by_id'],
        ['news_articles', 'author_id'],
        ['news_articles', 'news_category_id'],
        ['news_articles', 'updated_by_id'],
        ['olympic_results', 'updated_by_id'],
        ['page_meta', 'updated_by_id'],
        ['partners', 'updated_by_id'],
        ['role_has_permissions', 'role_id'],
        ['roles', 'updated_by_id'],
        ['standing_committees', 'updated_by_id'],
        ['sub_committees', 'updated_by_id'],
        ['tournaments', 'created_by_id'],
        ['tournaments', 'published_by_id'],
        ['tournaments', 'updated_by_id'],
        ['users', 'member_federation_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, fn ($t) => $t->index($column));
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, fn ($t) => $t->dropIndex([$column]));
        }
    }
};
