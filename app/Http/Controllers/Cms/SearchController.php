<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Faq;
use App\Models\GalleryItem;
use App\Models\MemberFederation;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pencarian lintas modul untuk kotak di topbar.
 *
 * ── Ia TIDAK ada di wireframe ──
 *
 * Topbar yang digambar (`251:1212`) hanya memuat matahari dan lonceng; kotak
 * "Search" yang ada di `252:2375` adalah penyaring DI DALAM halaman daftar,
 * dan itu sudah lama ada. Ini penambahan atas permintaan pemilik repo
 * 2026-09-10, dicatat sebagai penyimpangan di `docs/PROGRESS.md`.
 *
 * ── Izin disaring per modul, bukan sekali di depan ──
 *
 * Tiap kelompok hasil hanya dicari kalau orangnya boleh melihat modulnya.
 * Kalau tidak, satu kotak pencarian jadi jalan memutar untuk membaca judul
 * yang seluruh sidebarnya sudah disembunyikan darinya — dan judul adalah isi.
 * `super-admin` lolos lewat `Gate::before` seperti di mana pun.
 *
 * ── Tiap hasil menaut ke tempat yang benar-benar bisa ia buka ──
 *
 * Sebagian modul punya layar BACA (`/news/{id}`), sebagian hanya layar SUNTING
 * (`/tournaments/{id}/edit`) yang menuntut izin `.update`. Menaut ke yang kedua
 * untuk orang yang cuma boleh membaca akan menghasilkan daftar hasil yang
 * setengahnya 403 — dan yang terbaca orangnya adalah "pencariannya rusak".
 * Karena itu tujuan dihitung per hasil: layar baca kalau ada, layar sunting
 * kalau ia boleh menyunting, dan kalau tidak dua-duanya, daftar modulnya dengan
 * kata kuncinya sudah terisi (`?q=`) — selalu mendarat di sesuatu.
 */
class SearchController extends Controller
{
    /** Cukup untuk mengenali, tidak cukup untuk jadi halaman daftar kedua. */
    private const PER_GROUP = 5;

    /**
     * Di bawah dua huruf tidak dicari sama sekali.
     *
     * Satu huruf cocok dengan hampir seluruh tabel, jadi yang kembali bukan
     * jawaban melainkan lima baris pertama tiap modul — dan itu tetap menuntut
     * delapan query untuk tiap ketikan.
     */
    private const MIN_LENGTH = 2;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return response()->json(['groups' => []]);
        }

        $groups = [];

        foreach ($this->sources() as $source) {
            if (! $request->user()->can($source['module'].'.view')) {
                continue;
            }

            $rows = $this->look($source, $term);

            if ($rows === []) {
                continue;
            }

            $groups[] = [
                'module' => $source['module'],
                'label' => $source['label'],
                'items' => $rows,
            ];
        }

        return response()->json(['groups' => $groups]);
    }

    /**
     * Modul yang ikut dicari, beserta di kolom mana.
     *
     * `base` dipakai dua kali — untuk merakit tujuan dan untuk daftar cadangan
     * `?q=` — jadi ia satu nilai, bukan dua string yang mirip.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sources(): array
    {
        return [
            [
                'module' => 'news', 'label' => 'News Articles', 'base' => '/news',
                'model' => NewsArticle::class, 'columns' => ['title'], 'show' => true,
                'note' => fn (NewsArticle $a) => $a->category?->name,
                'with' => ['category:id,name'],
            ],
            [
                'module' => 'tournaments', 'label' => 'Events & Tournaments', 'base' => '/tournaments',
                'model' => Tournament::class, 'columns' => ['name', 'city', 'country'], 'show' => false,
                'note' => fn (Tournament $t) => $t->city,
            ],
            [
                'module' => 'documents', 'label' => 'Documents', 'base' => '/documents',
                'model' => Document::class, 'columns' => ['title'], 'show' => true,
                'note' => fn (Document $d) => $d->category,
            ],
            [
                'module' => 'federations', 'label' => 'Federations & Members', 'base' => '/federations',
                'model' => MemberFederation::class, 'columns' => ['name', 'country'], 'show' => false,
                'note' => fn (MemberFederation $f) => $f->country,
            ],
            [
                'module' => 'faq', 'label' => 'FAQ', 'base' => '/faq',
                'model' => Faq::class, 'columns' => ['question'], 'show' => true,
                'label_column' => 'question',
                'note' => fn (Faq $f) => $f->category?->name,
                'with' => ['category:id,name'],
            ],
            [
                'module' => 'gallery', 'label' => 'Gallery', 'base' => '/gallery',
                'model' => GalleryItem::class, 'columns' => ['alt'], 'show' => false,
                'label_column' => 'alt',
                'note' => fn (GalleryItem $g) => $g->event?->name,
                'with' => ['event:id,name'],
            ],
            [
                'module' => 'news', 'label' => 'News Categories', 'base' => '/news/categories',
                'model' => NewsCategory::class, 'columns' => ['name'], 'show' => false,
                'label_column' => 'name', 'list_only' => true,
            ],
            [
                // Nama DAN surel: orang mencari rekannya dengan salah satu dari
                // keduanya, dan yang diingat biasanya justru yang bukan nama.
                'module' => 'users', 'label' => 'User Management', 'base' => '/users',
                'model' => User::class, 'columns' => ['name', 'email'], 'show' => false,
                'note' => fn (User $u) => $u->email,
            ],
        ];
    }

    /**
     * Satu kelompok hasil.
     *
     * @param  array<string, mixed>  $source
     * @return array<int, array<string, mixed>>
     */
    private function look(array $source, string $term): array
    {
        /** @var class-string<Model> $model */
        $model = $source['model'];
        $labelColumn = $source['label_column'] ?? ($source['columns'][0]);

        $rows = $model::query()
            ->with($source['with'] ?? [])
            ->where(function (Builder $query) use ($source, $term) {
                foreach ($source['columns'] as $column) {
                    // `ILIKE` bukan `LIKE`: ini PostgreSQL, dan `LIKE` di sana
                    // peka huruf besar-kecil. Mencari "dwf" tidak akan pernah
                    // menemukan "DWF Annual Report".
                    $query->orWhere($column, 'ILIKE', '%'.$term.'%');
                }
            })
            ->limit(self::PER_GROUP)
            ->get();

        return $rows
            ->map(fn (Model $row) => [
                'id' => $row->getKey(),
                'label' => (string) $row->{$labelColumn},
                'note' => isset($source['note']) ? $source['note']($row) : null,
                'href' => $this->destination($source, $row, $term),
            ])
            ->all();
    }

    /**
     * Ke mana sebuah hasil membawa orangnya — lihat komentar kelas.
     *
     * @param  array<string, mixed>  $source
     */
    private function destination(array $source, Model $row, string $term): string
    {
        if (($source['list_only'] ?? false) === true) {
            return $source['base'].'?q='.urlencode($term);
        }

        if ($source['show'] === true) {
            return $source['base'].'/'.$row->getKey();
        }

        if (request()->user()->can($source['module'].'.update')) {
            return $source['base'].'/'.$row->getKey().'/edit';
        }

        return $source['base'].'?q='.urlencode($term);
    }
}
