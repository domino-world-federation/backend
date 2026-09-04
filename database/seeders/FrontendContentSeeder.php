<?php

namespace Database\Seeders;

use App\Models\BoardMember;
use App\Models\Champion;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqPlacement;
use App\Models\FederationStat;
use App\Models\HeritageMilestone;
use App\Models\MemberFederation;
use App\Models\NewsArticle;
use App\Models\NewsCategory;
use App\Models\OlympicResult;
use App\Models\PageMeta;
use App\Models\Partner;
use App\Models\SiteSetting;
use App\Models\StandingCommittee;
use App\Models\SubCommittee;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Mengisi backoffice dengan isi yang SAMA dengan mock situs publik.
 *
 * Sumbernya `../../landing-page-nuxt/app/lib/api/mock/index.ts`. Gunanya bukan
 * sekadar "ada datanya": begitu `routes/api.php` dibuat, situs publik bisa
 * ditukar dari mock ke API tanpa halamannya berubah tampilan — dan setiap
 * selisih yang muncul berarti kontraknya yang meleset, bukan datanya.
 *
 * ── Yang TIDAK ikut: berkas gambar. ──
 *
 * Mock menyimpan path seperti `/assets/about/heritage-card-01.png`, dan berkas
 * itu hidup di repo situs publik, bukan di `storage/` backoffice. Menyalin
 * path-nya ke sini menghasilkan kolom yang menunjuk berkas yang tidak ada —
 * tautan rusak yang terlihat seperti data yang benar. Kolom gambarnya
 * dibiarkan kosong sampai ada yang mengunggahnya lewat layarnya.
 *
 * ── Dua nama yang sengaja TIDAK disalin apa adanya. ──
 *
 * R11 dan R16 di `../../landing-page-nuxt/docs/PRD.md` mencatat bahwa sebagian
 * potret di mock adalah foto tokoh publik nyata yang dinamai pengurus dan juara
 * federasi ini. Karena berkasnya memang tidak ikut (lihat di atas), yang
 * tersisa cuma teksnya — dan teks itu tulisan desainer, bukan klaim tentang
 * orang yang bisa dikenali dari fotonya.
 *
 * Aman dijalankan berulang: semuanya `updateOrCreate` dengan kunci alami.
 */
class FrontendContentSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->stats();
        $this->partners();
        $this->heritage();
        $this->board();
        $this->subCommittees();
        $this->standingCommittees();
        $this->champions();
        $this->olympicResults();
        $this->federations();
        $this->tournaments();
        $this->pageMeta();
        $this->faqs();
        $this->homeCopy();
        $this->news();
    }

    /**
     * `MOCK_STATS` (roda beranda) dan `MOCK_MEMBERSHIP_STATS` (hero halaman
     * anggota).
     *
     * Dua daftar, bukan satu, dan mock situs publik menjelaskan kenapa meski
     * isinya kini nyaris sama: keduanya endpoint berbeda, dan menyatukannya
     * berarti halaman yang terakhir disunting menentukan apa yang ditampilkan
     * halaman satunya.
     *
     * Barisnya DIGANTI, bukan ditambahkan — `delete()` dulu supaya angka contoh
     * dari seeder sebelumnya tidak berdampingan dengan yang benar-benar dipakai.
     */
    private function stats(): void
    {
        $home = [
            ['Continents', '6'],
            ['Member Federation', '142'],
            ['Regional', '1.420'],
            ['Annual Event', '850'],
        ];

        $members = [
            ['Continents', '6'],
            ['National Federation', '142'],
            ['Regional', '1.420'],
            ['Annual Events', '850+'],
        ];

        foreach ([FederationStat::SCOPE_HOME => $home, FederationStat::SCOPE_MEMBERS => $members] as $scope => $rows) {
            FederationStat::query()->where('scope', $scope)->delete();

            foreach ($rows as $index => [$label, $value]) {
                FederationStat::query()->create([
                    'scope' => $scope,
                    'label' => $label,
                    'value' => $value,
                    'is_active' => true,
                    'position' => $index + 1,
                ]);
            }
        }
    }

    /** `MOCK_PARTNERS` — delapan logo di strip beranda. */
    private function partners(): void
    {
        $names = [
            'Pertamina Fastron', 'DRX', 'BAIC', 'JHL Collection',
            'kart.inc', 'adamare The Villa', 'Bahn Hoft', 'LXVR',
        ];

        foreach ($names as $index => $name) {
            Partner::query()->updateOrCreate(
                ['name' => $name],
                [
                    // Logo WAJIB di validasi formulir, tapi seeder menulis
                    // langsung ke model — path kosong di sini berarti "belum
                    // diunggah", dan layarnya yang menuntutnya saat disunting.
                    'logo_path' => '',
                    // Pertanyaan terbuka #6 di PRD situs publik: alamat tujuan
                    // kedelapan logo belum diketahui.
                    'website_url' => null,
                    'is_active' => true,
                    'position' => $index + 1,
                ],
            );
        }
    }

    /** `MOCK_HERITAGE_MILESTONES` — timeline di `/about`. */
    private function heritage(): void
    {
        $rows = [
            ['1974', 'The Foundation', 'Representatives from 12 nations gathered in Geneva to formalize the first set of international rules and establish the DWF.'],
            ['1990', 'Inaugural World Cup', 'The first World Cup brought together national champions from every founding federation.'],
            ['2001', '75 Countries Joined', 'Membership passed seventy-five national bodies, making the federation genuinely global.'],
            ['2003', 'DWF Championship World Tour', 'The first DWF Championship World Tour with 80 countries joined.'],
        ];

        foreach ($rows as $index => [$year, $title, $summary]) {
            HeritageMilestone::query()->updateOrCreate(
                ['year' => $year, 'title' => $title],
                ['summary' => $summary, 'is_active' => true, 'position' => $index + 1],
            );
        }
    }

    /**
     * `MOCK_BOARD_MEMBERS` — Executive Board di `/about`.
     *
     * Namanya memuat `\n` persis seperti di mock: kartunya merender dua baris,
     * dan itu keputusan yang ikut disalin, bukan artefak.
     */
    private function board(): void
    {
        $rows = [
            ["Dr. Salva\nLopez", 'President'],
            ["James\nHenderson", 'Vice President'],
            ["Elizabeth\nLi Tze", 'Secretary General'],
            ["Jennifer\nBachdzer", 'Secretary General'],
        ];

        foreach ($rows as $index => [$name, $role]) {
            BoardMember::query()->updateOrCreate(
                ['name' => $name],
                ['role' => $role, 'is_active' => true, 'position' => $index + 1],
            );
        }
    }

    /** `MOCK_SUB_COMMITTEES` — enam kartu label dan panah di `/about`. */
    private function subCommittees(): void
    {
        $names = [
            'Rules & Technical',
            'Global Development',
            'Ethics & Disciplinary',
            'Medical & Anti-Doping',
            'Marketing & Media',
            'Athletes Commission',
        ];

        foreach ($names as $index => $name) {
            SubCommittee::query()->updateOrCreate(
                ['name' => $name],
                // `href` dibiarkan kosong: halaman tujuannya memang belum ada,
                // dan mock pun tidak menyetelnya.
                ['href' => null, 'is_active' => true, 'position' => $index + 1],
            );
        }
    }

    /** `MOCK_STANDING_COMMITTEES` — tiga komite di `/governance`. */
    private function standingCommittees(): void
    {
        $rows = [
            ['Technical rules', ['International Rulebook Oversight', 'Equipment Standards', 'Tournament Sanctioning']],
            ['Medical & Anti-doping', ['WADA Compliance', 'Player Welfare Protocols', 'Mental Health in Sport']],
            ['Ethics & Compliance', ['Conflict of Interest', 'Disciplinary Tribunal', 'Good Governance Audit']],
        ];

        foreach ($rows as $index => [$name, $remit]) {
            StandingCommittee::query()->updateOrCreate(
                ['name' => $name],
                ['remit' => $remit, 'is_active' => true, 'position' => $index + 1],
            );
        }
    }

    /** `MOCK_CHAMPIONS` — Champions Hall. Potretnya sengaja tidak ikut (R16). */
    private function champions(): void
    {
        $rows = [
            ['2024 World Championship', "Marcus\nJohnson"],
            ['2023 World Championship', "Alicia\nBrown"],
            ['2024 European', "Devon\nClarke"],
            ['2024 Asian', "Priya\nRaman"],
        ];

        foreach ($rows as $index => [$event, $name]) {
            Champion::query()->updateOrCreate(
                ['event' => $event, 'name' => $name],
                ['is_active' => true, 'position' => $index + 1],
            );
        }
    }

    /** `MOCK_OLYMPIC_RESULTS`. */
    private function olympicResults(): void
    {
        $rows = [
            ['2025', 'Men’s Single Domino', 'Singles', 'Marcus Johnson', 'Jamaica'],
            ['2025', 'Doubles Domino', 'Doubles', 'Daniel Rodríguez & Carlos Martínez', 'Spain'],
            ['2025', 'Women’s Singles Domino', 'Singles', 'Alicia Brown', 'United Kingdom'],
        ];

        foreach ($rows as $index => [$year, $event, $category, $winners, $federation]) {
            OlympicResult::query()->updateOrCreate(
                ['year' => $year, 'event' => $event],
                [
                    'category' => $category,
                    'winners' => $winners,
                    'federation' => $federation,
                    'is_active' => true,
                    'position' => $index + 1,
                ],
            );
        }
    }

    /** `MOCK_MEMBER_FEDERATIONS` — direktori `/federation-members`. */
    private function federations(): void
    {
        $rows = [
            ['ORADO - Olahraga Domino Indonesia', 'Indonesia', 'national', 2025, 'Robert H. Miller', 'Miami, FL, United States', 'hi@orado.org', '+62 2123 4444'],
            ['USA Domino Federation', 'United States', 'national', 2019, 'Angela Fitzgerald', 'Atlanta, GA, United States', 'office@usadomino.org', '+1 404 555 0142'],
            ['Jamaica Domino Board', 'Jamaica', 'national', 2016, 'Devon Clarke', 'Kingston, Jamaica', 'board@jamaicadomino.jm', '+1 876 555 0119'],
            ['China Domino Association', 'China', 'national', 2021, 'Li Wei', 'Shanghai, China', 'contact@cda-domino.cn', '+86 21 5555 0170'],
            ['Federacion Mexicana de Domino', 'Mexico', 'national', 2018, 'Mateo Ruiz', 'Mexico City, Mexico', 'info@fmdomino.mx', '+52 55 5555 0123'],
        ];

        foreach ($rows as $index => [$name, $country, $tier, $joined, $president, $hq, $email, $phone]) {
            MemberFederation::query()->updateOrCreate(
                ['name' => $name],
                [
                    'country' => $country,
                    'tier' => $tier,
                    'joined_year' => $joined,
                    'president' => $president,
                    'headquarters' => $hq,
                    'email' => $email,
                    'phone' => $phone,
                    'is_active' => true,
                    'position' => $index + 1,
                ],
            );
        }
    }

    /**
     * `MOCK_TOURNAMENTS` — tiga turnamen yang dipakai rail dan halaman detail.
     *
     * Beberapa field yang diminta formulir tidak ada di mock (venue, kelayakan,
     * sistem kompetisi) karena kontrak frontend memang tidak memuatnya di
     * bentuk kartu. Yang diisi di sini nilai yang masuk akal, ditandai supaya
     * jelas ia bukan salinan.
     */
    private function tournaments(): void
    {
        $rows = [
            [
                'slug' => 'london-open-2026',
                'name' => 'London International Domino Open',
                'coverage' => 'Inter-continental',
                'starts_on' => '2026-09-18',
                'ends_on' => '2026-09-21',
                'city' => 'London',
                'country' => 'United Kingdom',
                'game_format' => 'Single 101, Double 101, 3 Round, Best of 3',
                'registration_starts_on' => '2026-06-01',
                'registration_ends_on' => '2026-08-14',
                'venue_name' => 'ExCeL London',
                'venue_address' => 'Royal Victoria Dock, 1 Western Gateway, London',
                'venue_lat' => 51.5081,
                'venue_lng' => 0.0294,
            ],
            [
                'slug' => 'dubai-masters-2026',
                'name' => 'Dubai Grand Masters Domino Series',
                'coverage' => 'Championship',
                'starts_on' => '2026-11-05',
                'ends_on' => '2026-11-09',
                'city' => 'Dubai',
                'country' => 'United Arab Emirates',
                'game_format' => 'Double 101, 3 Round, Best of 3',
                'registration_starts_on' => '2026-11-01',
                'registration_ends_on' => '2026-11-03',
                'venue_name' => 'Dubai World Trade Centre',
                'venue_address' => 'Sheikh Zayed Road, Dubai',
                'venue_lat' => 25.2258,
                'venue_lng' => 55.2867,
            ],
            [
                'slug' => 'winter-finals-championship-2026',
                'name' => 'Winter Finals Domino Championship',
                'coverage' => 'Regional qualifier',
                'starts_on' => '2026-01-20',
                'ends_on' => '2026-01-24',
                'city' => 'Madrid',
                'country' => 'Spain',
                'game_format' => 'Double 101, Best of 3',
                'registration_starts_on' => '2025-11-01',
                'registration_ends_on' => '2026-01-10',
                'venue_name' => 'Madrid Arena',
                'venue_address' => 'Av. de Portugal, Madrid',
                'venue_lat' => 40.4108,
                'venue_lng' => -3.7275,
            ],
        ];

        foreach ($rows as $row) {
            Tournament::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + [
                    'rules_format' => 'Double 101',
                    'overview' => 'The federation brings together its member bodies for a full week of competition, with qualification rounds, a knockout stage, and an awards ceremony on the closing day.',
                    'eligibility' => 'Open to all DWF member federations',
                    'registration_method' => 'Through national federation',
                    'participant_type' => 'Teams',
                    'competition_system' => '16 groups of four; top two advance to knockout',
                    'scoring' => 'First team to reach 101 points wins the match',
                    'status' => Tournament::STATUS_PUBLISHED,
                    'published_at' => now(),
                ],
            );
        }
    }

    /**
     * Meta SEO tiap halaman publik.
     *
     * Judul dan deskripsinya disalin dari `useSeoMeta` yang sekarang tertanam di
     * ke-15 berkas halaman Nuxt — jadi memindahkan halaman itu ke API tidak
     * mengubah satu kata pun yang dibaca mesin pencari.
     *
     * Rute DINAMIS (`/news/[slug]`, `/tournaments/[slug]`, `/page/[key]`) tidak
     * ikut: metanya lahir dari isi barisnya sendiri, dan satu baris di sini akan
     * mengklaim ribuan halaman yang semuanya berbeda.
     *
     * Gambar bagikannya kosong — belum ada satu pun di repo situs publik, dan
     * itu justru lubang yang layar ini dibangun untuk menutup.
     */
    private function pageMeta(): void
    {
        $rows = [
            ['*', 'Site default', 'Domino World Federation', 'The global governing body for domino — tournaments, rankings, and official federation resources.'],
            ['/', 'Home', 'Domino World Federation', 'The global governing body for domino — tournaments, rankings, and official federation resources.'],
            ['/about', 'About Us', 'About Us | Domino World Federation', 'The Domino World Federation is the international governing body for the sport of dominoes, representing over 80 national member associations across five continents.'],
            ['/domino', 'Domino', 'Domino | Domino World Federation', 'The rules, formats and regulations of sanctioned dominoes — singles and doubles play, referee guidelines, and the federation\'s official rulebook.'],
            ['/tournaments', 'Tournaments', 'Tournaments | Domino World Federation', 'Sanctioned domino tournaments — the season\'s highlighted event, the full calendar, competition regulations, past champions and Olympic results.'],
            ['/tournaments/all', 'All Tournaments', 'All Tournaments | Domino World Federation', 'Every sanctioned Domino World Federation tournament — open, upcoming, under way and completed — with dates, venues and entry states.'],
            ['/news', 'News', 'News | Domino World Federation', 'Federation news, press releases and publications — tournament results, governance decisions, development programmes, and the media archive.'],
            ['/news/all', 'All News', 'All News | Domino World Federation', 'The full Domino World Federation news archive — tournament results, governance decisions, membership changes and development programmes, filterable by category.'],
            ['/gallery', 'Gallery', 'Gallery | Domino World Federation', 'Photographs and films from Domino World Federation events — world championships, continental masters, and the federation\'s own documentaries.'],
            ['/federation-members', 'Members', 'Members | Domino World Federation', 'The federation\'s global membership — national bodies across six continents, what membership grants, and the four-step pathway to DWF recognition.'],
            ['/player-membership', 'Player Membership', 'Player Membership | Domino World Federation', 'The DWF ID — one verified identity across the federation\'s network. What it is, what it grants, who can apply and how the application works.'],
            ['/governance', 'Governance', 'Governance | Domino World Federation', 'How the Domino World Federation is run — its mandate and mission, standing committees, statutes and constitution, the 2026–2029 strategic plan, and the public governance repository.'],
            ['/integrity', 'Integrity', 'Integrity | Domino World Federation', 'The federation\'s zero-tolerance policy on competitive integrity — core principles, the code of ethics, how the Tile-Trace engine detects manipulation, how a report is handled, and how to file one.'],
            ['/development', 'Development', 'Development | Domino World Federation', 'How the federation grows the game — youth programmes in partner schools, referee and coaching certification, grassroots initiatives, and support for national member bodies.'],
            ['/contact', 'Contact', 'Contact | Domino World Federation', 'Reach the Domino World Federation for general enquiries, membership information, tournament support, partnerships and media requests.'],
            ['/page/faq', 'FAQ', 'FAQ | Domino World Federation', 'Answers to common questions about sanctioned dominoes — how tournaments are entered, what the standard set holds, and how a hand is played and scored.'],
        ];

        foreach ($rows as $index => [$route, $label, $title, $description]) {
            PageMeta::query()->updateOrCreate(
                ['route' => $route],
                [
                    'label' => $label,
                    'title' => $title,
                    'description' => $description,
                    'position' => $index,
                ],
            );
        }
    }

    /**
     * FAQ, kategorinya, dan penempatannya di tiap halaman.
     *
     * Pertanyaannya disalin dari EMPAT berkas di situs publik yang selama ini
     * berdiri sendiri-sendiri: `content/home/faq.ts`, `content/domino/faq.ts`,
     * `content/tournaments/faq.ts`, dan `content/faq/items.ts` (halaman FAQ
     * lengkap). Yang muncul di lebih dari satu tempat disemai SEKALI lalu
     * ditempelkan ke beberapa halaman — itu justru bentuk yang dituntut modul
     * ini, dan "Are verbal communications allowed in doubles?" adalah contoh
     * hidupnya: ia nomor 2 di Domino DAN nomor 2 di Tournament, dua angka yang
     * sebelum 2026-09-03 tidak punya tempat untuk disimpan terpisah.
     *
     * Lima kategori disemai walau baru dua yang terpakai. Situs publik
     * menggambar tabnya dari pertanyaan yang ADA, bukan dari daftar ini, jadi
     * laci yang kosong tidak muncul — dan kategori yang siap dipakai lebih
     * berguna daripada kategori yang harus dibuat dulu sebelum bisa memfilekan
     * pertanyaan pertamanya.
     *
     * Jawaban bertanda `TODO(copy)` di situs publik ikut apa adanya, lengkap
     * dengan penandanya: menulis ulang di sini berarti dua naskah placeholder
     * yang berbeda untuk satu pertanyaan.
     */
    private function faqs(): void
    {
        // Penempatan di ketiga halaman DIGANTI, bukan ditambahkan — sama
        // seperti `stats()` di atas, dan karena alasan yang sama: tanpa ini,
        // baris contoh dari seeder lama berdiri berdampingan dengan yang benar
        // pada peringkat yang sama, dan dua baris di posisi 2 berarti urutan
        // yang ditentukan kebetulan. FAQ-nya sendiri tidak disentuh; yang
        // dilepas cuma tempelannya ke halaman.
        FaqPlacement::query()->whereIn('page', Faq::PAGES)->delete();

        $categories = collect([
            'General', 'DWF', 'Tournament', 'Membership', 'Development',
        ])->mapWithKeys(fn (string $name, int $index) => [
            Str::slug($name) => FaqCategory::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'position' => $index + 1],
            ),
        ]);

        // [pertanyaan, kategori, jawaban, penempatan => peringkat]
        $rows = [
            [
                'What is Domino?',
                'general',
                'Dominoes is a tile game played everywhere from street corners to sanctioned arenas. The DWF governs its <strong>competitive form</strong> — one standard set, one scoring system, and a single rulebook every sanctioned match is played under.',
                ['home' => 1],
            ],
            [
                'How do I join a tournament?',
                'tournament',
                'To join a tournament, <strong>browse the available tournaments</strong> and <strong>select one you&rsquo;re interested</strong> in. If registration is open, <strong>click &ldquo;Register for Tournament&rdquo;</strong> and complete the required information before the registration deadline.',
                ['home' => 2],
            ],
            [
                'Where can I find the tournament rules?',
                'tournament',
                'Every sanctioned match is played under the <strong>Standard International Rulebook</strong>, published in the resource library above. National federations may add local provisions for their own competitions, but none of them override the rulebook.',
                ['home' => 3],
            ],
            [
                "What constitutes a 'blocked' game?",
                'general',
                'A game is <strong>blocked</strong> when no player can place a tile and the boneyard is empty. The hand ends there, and the player holding the lowest pip total takes the points still in their opponents&rsquo; hands.',
                ['domino' => 1],
            ],
            [
                'Are verbal communications allowed in doubles?',
                'tournament',
                '<strong>No</strong>. Verbal communication between doubles partners <strong>is not allowed during play</strong>. Players must not provide instructions, coaching, or strategic guidance to their partner while a point is in progress. This rule is in accordance with <em>The Law of Domino &ndash; Tag Team</em>. Failure to comply may result in penalties in accordance with the tournament rules.',
                ['domino' => 2, 'tournament' => 2],
            ],
            [
                'How are professional tiles different?',
                'general',
                'Sanctioned play uses a <strong>standard 6-6 set</strong> manufactured to the federation&rsquo;s equipment specification, so weight, dimensions and pip depth are identical at every table. The standard is published as DWF-ES1.',
                ['domino' => 3],
            ],
            [
                'How do I register for the tournament?',
                'tournament',
                'To join a tournament, <strong>browse the available tournaments</strong> and <strong>select one you&rsquo;re interested</strong> in. If registration is open, <strong>click &ldquo;Register for Tournament&rdquo;</strong> and complete the required information before the registration deadline.',
                ['tournament' => 1],
            ],
            [
                'When will the tournament results be announced?',
                'tournament',
                'Standings are published <strong>as each round is scrutineered</strong>, and the final result is confirmed once the referees&rsquo; sheets are signed off — usually within an hour of the last hand.',
                ['tournament' => 3],
            ],
            [
                'What equipment is needed to play dominoes?',
                'general',
                'A sanctioned match needs a <strong>standard double-six set</strong> manufactured to the federation&rsquo;s equipment specification, DWF-ES1, a table that seats four, and a scoresheet.',
                [],
            ],
            [
                'How many tiles are included in a standard domino set?',
                'general',
                'A standard double-six set holds <strong>28 tiles</strong> — every pairing of two numbers from blank to six, each appearing exactly once.',
                [],
            ],
            [
                'How is the first player decided?',
                'general',
                'The player holding the <strong>highest double</strong> opens the first hand. After that the winner of the previous hand leads, and where nobody holds a double the tiles are reshuffled and drawn again.',
                [],
            ],
            [
                'How does a player make a valid move?',
                'general',
                'A move is valid when the tile placed <strong>matches an open end of the layout</strong> — one half of it carries the number showing at that end. Doubles are laid across the line and are played on like any other end.',
                [],
            ],
            [
                'What happens if a player cannot make a move?',
                'general',
                'They <strong>draw from the boneyard</strong> until a tile they can place comes out. Once the boneyard is empty and nothing matches, the turn passes and play moves on.',
                [],
            ],
            [
                'What is the boneyard in dominoes?',
                'general',
                'The <strong>boneyard</strong> is the pool of tiles left face down once every player has drawn their hand. It is what a player draws from when they cannot move, and when it runs out the only move left is to pass.',
                [],
            ],
            [
                'How does a round of dominoes end?',
                'general',
                'A round ends when a player lays their <strong>last tile</strong>, or when nobody can move and the game is blocked. The hand is then scored on the pips still held in the other players&rsquo; hands.',
                [],
            ],
        ];

        foreach ($rows as $index => [$question, $categorySlug, $answer, $placements]) {
            $faq = Faq::query()->updateOrCreate(
                ['question' => $question],
                [
                    'faq_category_id' => $categories[$categorySlug]->id,
                    'answer' => "<p>{$answer}</p>",
                    'is_active' => true,
                    'position' => $index + 1,
                ],
            );

            foreach ($placements as $page => $position) {
                FaqPlacement::query()->updateOrCreate(
                    ['faq_id' => $faq->id, 'page' => $page],
                    ['position' => $position],
                );
            }
        }
    }

    /**
     * Naskah hero dan band ajakan penutup halaman depan.
     *
     * Disalin verbatim dari `content/home/hero.ts` (`22:789`) dan
     * `content/home/join.ts` (`56:4683`, `56:4682`, `56:4685`) — sampai
     * 2026-09-03 keduanya tertulis keras di repo situs publik, dan mengubah
     * satu kata berarti menyunting kode lalu deploy ulang.
     *
     * `closing_headline` disimpan DIPISAH BARIS BARU. Figma memutus barisnya
     * secara eksplisit dan putusan itu bagian dari komposisinya; API yang
     * memecahnya jadi larik.
     *
     * Tautan tombol kedua hero diisi `#` apa adanya — di situs publik memang
     * masih begitu, dan menebak tujuannya di sini berarti CMS memutuskan
     * sesuatu yang belum diputuskan siapa pun.
     */
    private function homeCopy(): void
    {
        SiteSetting::putMany([
            'hero_tagline' => 'Domino World Federation',
            'hero_headline' => 'Dominoes Without Borders',
            'hero_mission' => 'To unite the world through dominoes by connecting nations, growing the game, and setting fair global standards for every player.',
            'hero_accountability' => 'Designed and operates under a rigorous framework of accountability',
            'hero_primary_cta' => 'Explore Membership',
            'hero_primary_cta_url' => '/federation-members',
            'hero_secondary_cta' => 'Official Rules',
            'hero_secondary_cta_url' => '#',

            'closing_headline' => "Bring Your Nation\nTo The World Stage",
            'closing_body' => 'We are currently accepting applications for new associate and full member federations. Benefit from technical support, sanctioned event hosting, and global ranking integration.',
            'closing_cta' => 'Get In Touch',
            'closing_cta_url' => '/contact',
        ], SiteSetting::GROUP_HOME);
    }

    /**
     * Berita dan kategorinya.
     *
     * Disalin dari `MOCK_NEWS` di `../landing-page-nuxt/app/lib/api/mock/index.ts`
     * — sebelas artikel, enam kategori — supaya penukaran mock → API tidak
     * mengubah satu kata pun yang terbaca di halaman. Judul, kutipan, slug, dan
     * tanggal terbitnya sama persis; slug ikut apa adanya karena ia yang jadi
     * URL, dan URL yang berubah adalah tautan yang mati.
     *
     * Sebelum ini beritanya disemai `DatabaseSeeder` dengan tujuh judul karangan
     * dan empat kategori yang tidak ada satu pun di mock. Akibatnya penyaring
     * kategori di `/news/all` menampilkan daftar yang berbeda dari yang
     * dirancang halamannya.
     *
     * Dua baris TAMBAHAN yang tidak ada di mock — satu draft dan satu
     * terjadwal — supaya layar daftar di backoffice memperlihatkan keempat
     * keadaan Visibility, bukan cuma "published". Keduanya tidak akan pernah
     * muncul di situs publik, dan itu justru yang diuji.
     *
     * Gambarnya TIDAK ditanam di sini: `php artisan dwf:demo-images` yang
     * membuatnya, terpisah karena ia menulis berkas biner dan hanya berguna di
     * lingkungan contoh.
     */
    private function news(): void
    {
        $author = User::query()->orderBy('id')->first();

        $categories = collect(['Tournament', 'Governance', 'Development', 'Federation', 'Ranking', 'Officiating'])
            ->mapWithKeys(fn (string $name, int $index) => [
                $name => NewsCategory::query()->updateOrCreate(
                    ['slug' => Str::slug($name)],
                    ['name' => $name, 'is_active' => true, 'position' => $index + 1],
                )->id,
            ]);

        // [slug, judul, kutipan, kategori, terbit, disorot]
        $rows = [
            ['world-championship-qualifiers-conclude', 'World Championship Qualifiers Conclude in Jakarta', 'Sixty-four players advance to the main draw after three days of continental qualifying.', 'Tournament', '2026-08-12 10:00', true],
            ['new-refereeing-standard-published', 'New Refereeing Standard Published for 2027', 'The updated rulebook clarifies scoring disputes and introduces a revised timing protocol.', 'Governance', '2026-08-08 09:30', false],
            ['digital-learning-portal-100k-users', 'Digital Learning Portal Reaches 100K Users Milestone', 'The free tuition platform passes a hundred thousand registered learners in its first full year.', 'Development', '2026-08-06 09:00', false],
            ['three-federations-join-dwf', 'Three National Federations Join DWF', 'Membership passes eighty-four as domino continues its expansion across three continents.', 'Federation', '2026-07-29 14:15', false],
            ['youth-development-programme-launch', 'Youth Development Programme Launches', 'A structured pathway for players under eighteen begins in twelve member nations.', 'Development', '2026-07-21 08:00', true],
            ['annual-congress-summary', 'Annual Congress Summary and Resolutions', 'Delegates approved the revised statutes and confirmed the 2027 competition calendar.', 'Federation', '2026-07-10 11:45', true],
            ['world-ranking-system-revised', 'World Ranking System Revised for Next Season', 'Points now decay over twelve months, so a title defended counts for more than a title held.', 'Ranking', '2026-06-28 13:20', true],
            ['referee-certification-intake-opens', 'Referee Certification Intake Opens Worldwide', 'Applications are open in every member federation, with the first assessments held in October.', 'Officiating', '2026-06-15 07:00', true],
            ['oceania-school-participation-up-40-percent', 'Oceania Region Sees 40% Increase in School Participation', 'Partner schools across the region report their strongest intake since the youth pathway opened.', 'Development', '2026-03-06 08:00', true],
            ['new-equipment-standards-2025-championships', 'New Equipment Standards Released for 2025 Championships', 'Tile dimensions, weight tolerance and table surfaces are specified for every sanctioned event.', 'Development', '2025-03-14 10:30', false],
            ['grade-a-referee-seminar-registration', 'Registration Opens for Grade A Referee Seminar', 'Continental referees may apply for the elite assessment, held over four days with a written exam.', 'Development', '2025-02-22 09:00', false],
        ];

        foreach ($rows as [$slug, $title, $excerpt, $category, $publishedAt, $highlight]) {
            NewsArticle::query()->updateOrCreate(['slug' => $slug], [
                'news_category_id' => $categories[$category],
                'author_id' => $author?->id,
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => "<p>{$excerpt}</p><p>Isi contoh untuk pengembangan lokal. Ganti sebelum tayang.</p>",
                'is_highlighted' => $highlight,
                'status' => 'published',
                'published_at' => $publishedAt,
            ]);
        }

        // Dua keadaan yang tidak ada di mock, supaya layar daftar backoffice
        // memperlihatkan lebih dari satu warna pil Visibility.
        NewsArticle::query()->updateOrCreate(['slug' => 'draft-broadcast-partnership'], [
            'news_category_id' => $categories['Federation'],
            'author_id' => $author?->id,
            'title' => 'Draft: partnership with a global broadcast network',
            'excerpt' => 'Belum siap tayang — dipakai untuk melihat keadaan Draft di layar daftar.',
            'body' => '<p>Isi contoh untuk pengembangan lokal.</p>',
            'is_highlighted' => false,
            'status' => 'draft',
            'published_at' => null,
        ]);

        NewsArticle::query()->updateOrCreate(['slug' => 'scheduled-2027-qualification-calendar'], [
            'news_category_id' => $categories['Tournament'],
            'author_id' => $author?->id,
            'title' => 'Scheduled: 2027 qualification calendar',
            'excerpt' => 'Terjadwal — dipakai untuk melihat keadaan Scheduled di layar daftar.',
            'body' => '<p>Isi contoh untuk pengembangan lokal.</p>',
            'is_highlighted' => false,
            'status' => 'scheduled',
            'published_at' => now()->addWeeks(2),
        ]);
    }
}
