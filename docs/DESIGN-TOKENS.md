# Design Tokens — DWF Backoffice

Diekstraksi langsung dari Figma **DWF Wireframe**, `fileKey`
`Cwd12fOcsUXmyLF4q1yWVP`, kanvas **Backoffice**, pada **2026-08-27**.

Seluruh nilai di §1–§4 **terbaca dari desain**. Yang diturunkan atau
disubstitusi ditandai eksplisit di §5 dan §6.

Terpasang di [`../resources/css/app.css`](../resources/css/app.css) sebagai
`@theme` Tailwind v4. **Dilarang menulis nilai warna/ukuran langsung di
komponen** — selalu lewat token.

> **Bukan token situs publik.** `../../landing-page-nuxt/docs/DESIGN-TOKENS.md`
> memakai Bebas Neue + Inter dengan palet emas/gelap. Backoffice memakai palet
> Carbon (CoolGray) dengan Roboto. Keduanya sengaja berbeda; jangan disatukan.

---

## 1. Warna

### CoolGray — dasar seluruh area konten

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--color-cool-10` | `#F2F4F8` | latar field, latar header tabel |
| `--color-cool-20` | `#DDE1E6` | garis tabel, garis kartu |
| `--color-cool-30` | `#C1C7CD` | garis bawah field, garis tombol dashed |
| `--color-cool-40` | `#A2A9B0` | teks nonaktif, pemisah |
| `--color-cool-60` | `#697077` | teks sekunder, placeholder, deskripsi |
| `--color-cool-70` | `#4D5358` | label form |
| `--color-cool-80` | `#343A3F` | isi field terisi |
| `--color-cool-90` | `#21272A` | judul halaman, teks isi |
| `--color-cool-100` | `#121619` | isi sel tabel, tombol Filled |

### Primary

| Token | Nilai | Dipakai untuk |
|---|---|---|
| `--color-primary-60` | `#E1B762` | emas DWF — toggle aktif, halaman aktif pagination |
| `--color-primary-90` | `#001D6C` | ruas breadcrumb terakhir, angka pagination aktif |

### Sidebar

| Token | Nilai | Sumber |
|---|---|---|
| `--color-shell` | `#101010` | `252:3403` fill |
| `--color-shell-gradient-end` | `#636363` | `252:3402` gradient, mulai y=80 |
| `--color-shell-sub-active` | `#383838` | `252:3242` sub-item aktif |
| `--color-shell-main-active` | `#F5F5F5` | `252:3241` item utama aktif (pill terang) |
| `--color-shell-muted` | `#C2C2C2` | `252:3268` baris email |

### Permukaan & status

| Token | Nilai | |
|---|---|---|
| `--color-canvas` | `#E8E8E8` | latar area konten |
| `--color-surface` | `#FFFFFF` | kartu, topbar, tabel |
| `--color-on-inverse` | `#FFFFFF` | tinta di atas permukaan terbalik (`bg-cool-100` / `bg-cool-90`) — tombol filled, pil filter aktif |
| `--color-danger` | `#DA1E28` | asterisk field wajib (`ts1` di Figma), galat validasi |

### Chrome rich-text editor

Skala slate, **berbeda** dari CoolGray — memang begitu di wireframe
(`341:4784`, toolbar editor jawaban FAQ). Belum terpakai; modul yang
membutuhkannya (News, FAQ) belum dibangun.

| Token | Nilai |
|---|---|
| `--color-editor-0` | `#FFFFFF` |
| `--color-editor-5` | `#F8FAFC` |
| `--color-editor-20` | `#E2E8F0` |
| `--color-editor-30` | `#CBD5E1` |
| `--color-editor-60` | `#475569` |
| `--color-editor-80` | `#1E293B` |
| `--shadow-editor` | `0 2px 4px -2px rgb(23 23 23 / .06), 0 4px 8px -2px rgb(23 23 23 / .1)` |

---

## 2. Font

Tiga family, masing-masing punya wilayahnya. Self-hosted lewat
`laravel-vite-plugin` (provider Bunny) — tidak ada request ke pihak ketiga saat
runtime.

| Token | Family | Weight | Wilayah |
|---|---|---|---|
| `--font-sidebar` | **Inter** | 400, 500, 600 | sidebar saja |
| `--font-content` | **Roboto** | 400, 500, 700 | seluruh area konten |
| `--font-editor` | **Plus Jakarta Sans** | 600, 700 | chrome editor saja |

`--font-sans` dijembatani ke `--font-content` supaya `font-sans` bawaan
Tailwind tidak menunjuk ke family yang salah.

> **`@fonts` wajib ada di `app.blade.php`, sebelum `@vite`.** Tanpa itu
> `@font-face`-nya tidak pernah terpasang dan seluruh backoffice diam-diam
> jatuh ke font sistem — build tetap lolos.

---

## 3. Skala tipografi

Nama utility mengikuti nama gaya di Figma supaya bisa dicocokkan satu-satu.

| Utility | Gaya Figma | Ukuran / weight / line-height |
|---|---|---|
| `text-heading-4` | Heading/4 | 24 / 700 / 1.1 |
| `text-heading-6` | Heading/6 | 18 / 700 / 1.1 |
| `text-subtitle-s` | Subtitle/S | 14 / 500 / 1.1 |
| `text-body-m` | Body/M | 16 / 400 / 1.4 |
| `text-body-s` | Body/S | 14 / 400 / 1.4 |
| `text-body-xs` | Body/XS | 12 / 400 / 1.4 |
| `text-button-m` | Button/M | 16 / 500 / 1 · tracking `.0313em` |
| `text-button-s` | Button/S | 14 / 500 / 1 · tracking `.0357em` |
| `text-nav-l` | Inter/Body/Large/16/Regular | 16 / 400 / 1.5 |
| `text-nav-l-semibold` | Inter/Body/Large/16/Semibold | 16 / 600 / 1.5 |
| `text-nav-m` | Inter/Body/Medium/14/Regular | 14 / 400 / 1.5 |
| `text-nav-m-semibold` | Inter/Body/Medium/14/Semibold | 14 / 600 / 1.5 |

---

## 4. Metrik

| Bagian | Nilai | Sumber |
|---|---|---|
| Sidebar | lebar `312`, padding `24 16`, gap `16` | `252:3403` |
| Menu item | padding `8 12`, radius `4`, gap `12` | `239:3016` |
| Sub-item | padding `8 12 8 48` | `239:3037` |
| Topbar | tinggi `56` | `251:1197` |
| Gutter konten | `24` | `252:2368` di `x=24, y=80` |
| Kolom konten | `1080` | `252:2368` |
| Field | tinggi `48`, padding `12 16`, latar CoolGray/10, garis bawah `1` CoolGray/30 | `249:529` |
| Textarea | tinggi `96`, padding `14 16` | `249:530` |
| Tombol M | tinggi `48`, padding `16 12`, teks berjarak `0 16` | `251:879` |
| Tombol S | tinggi `40`, padding `8 12` | `251:883` |
| Sel tabel | padding `16 12`, garis `1` CoolGray/20 | `252:1746` |
| Header tabel | latar CoolGray/10, `Subtitle/S` | `252:1750` |
| Toggle | kotak sentuh `32×20`, track `32×16`, knob `12` | `252:1679` |
| Checkbox / radio | kotak sentuh `20×20`, bentuk `16×16` | `252:1670` / `252:1675` |
| Dialog konfirmasi | lebar `544`, padding `32 32 24`, gap `32`, radius `12` | `330:8004` |

---

## 5. Mode gelap — DITURUNKAN, belum diverifikasi

Wireframe hanya menggambar mode terang, tapi topbar-nya (`251:1208`) memasang
ikon matahari — jadi toggle terang/gelap memang diminta desain.

Palet gelapnya **saya turunkan** dengan membalik skala CoolGray dan menggelapkan
`--color-canvas` / `--color-surface`. Nilainya ada di blok `[data-theme='dark']`
di `app.css`. **Belum dikonfirmasi ke desainer.** Kalau lebih baik ditunda,
hapus tombolnya di `AdminLayout.vue`; tokennya boleh tetap tinggal.

**Konsekuensi yang gampang terlewat: `text-white` bukan warna yang aman.**
Karena skalanya dibalik, `cool-100` jadi `#FFFFFF` di mode gelap — jadi teks
putih di atas `bg-cool-100` (tombol filled, pil rentang di dashboard) berubah
jadi putih-di-atas-putih dan elemennya lenyap tanpa jejak di build maupun
typecheck. Yang benar `text-on-inverse`: tokennya ikut membalik bersama
latarnya. `text-white` hanya sah di atas permukaan yang TIDAK membalik —
`bg-shell` di sidebar dan `AuthLayout`, dan `bg-primary-60` di `AppCheckbox`.

Tema disimpan di `localStorage` (`dwf.theme`) dan dipasang skrip inline di
`<head>` sebelum CSS termuat — kalau menunggu Vue hidup, halaman berkedip terang
dulu di setiap muat.

## 6. Ikon — DISUBSTITUSI ke Phosphor

Wireframe mencampur empat set ikon: Phosphor di sidebar, lalu jam-icons,
tabler, iconoir, dan feather di area konten. Semuanya dinormalkan ke
**Phosphor** (`@phosphor-icons/vue`) — satu library berarti satu ukuran, satu
berat, satu bentuk sudut.

| Di Figma | Dipakai |
|---|---|
| jam-icons `search` | `PhMagnifyingGlass` |
| jam-icons `plus` | `PhPlus` |
| jam-icons `chevron-down/left/right` | `PhCaretDown` / `PhCaretLeft` / `PhCaretRight` |
| jam-icons `more-horizontal-f` | `PhDotsThree` |
| feather `trash-2` | `PhTrash` |
| iconoir `edit` | `PhPencilSimple` |
| iconoir `bell` | `PhBell` |
| iconoir `add-media-image` | `PhImageSquare` |
| tabler `sun` | `PhSun` |
| tabler `file-upload` | `PhFileArrowUp` |
| bootstrap `check2` | `PhCheck` |
| iconoir `cancel` | `PhX` |

Nama ikon sidebar diterjemahkan lewat peta eksplisit di
[`../resources/js/Components/Sidebar/NavIcon.vue`](../resources/js/Components/Sidebar/NavIcon.vue).
Peta, bukan impor dinamis: impor dinamis akan menarik seluruh ~9.000 ikon
Phosphor ke dalam bundel demi 14 yang benar-benar dipakai.
