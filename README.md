# DWF Backoffice

Portal admin Domino World Federation, dan nanti juga rumah bagi API publik yang
dikonsumsi [`../landing-page-nuxt`](../landing-page-nuxt).

Desainnya: Figma **DWF Wireframe**, `fileKey` `Cwd12fOcsUXmyLF4q1yWVP`, kanvas
**Backoffice**.

Seluruh modul CMS di kanvas itu **sudah jadi**, berikut API publik `/api/v1`
yang dibaca situs Nuxt. Statusnya lengkap di
[docs/PROGRESS.md](docs/PROGRESS.md); yang harus diingat sebelum menyentuh
server ada di **[docs/PRODUCTION.md](docs/PRODUCTION.md)**.

## Stack

Laravel 13 · Inertia 3 · Vue 3.5 · TypeScript strict · Tailwind v4 ·
PostgreSQL 17 · **Bun** (package manager)

## Menjalankan

Prasyarat: PHP 8.3+ dengan `pdo_pgsql`, Composer, Bun, PostgreSQL yang hidup.

```bash
composer install
bun install

cp .env.example .env
php artisan key:generate

createdb dwf_backoffice
createdb dwf_backoffice_testing          # dipakai `php artisan test`
```

Isi `.env`:

```dotenv
DB_DATABASE=dwf_backoffice
DB_USERNAME=…                            # user Postgres kamu
DWF_ADMIN_EMAIL=admin@dwf-domino.org
DWF_ADMIN_PASSWORD=…                     # bebas; seeder menolak jalan kalau kosong

# Opsional — captcha di halaman login. Kosongkan untuk mematikannya.
RECAPTCHA_SITE_KEY=
RECAPTCHA_SECRET_KEY=

# Otentikasi dua langkah (Google Authenticator). Bawaannya menyala.
# `false` mematikannya untuk semua orang; per pengguna lewat
# kolom `users.two_factor_enabled`.
DWF_TWO_FACTOR=true

# Opsional — tombol ganti bahasa di topbar. Bawaannya mati; backoffice
# tampil dalam bahasa Inggris saja. Isi `true` untuk memunculkannya.
DWF_LOCALE_SWITCHER=false
```

### reCAPTCHA (opsional)

Ambil kuncinya di
[google.com/recaptcha/admin/create](https://www.google.com/recaptcha/admin/create):
pilih tipe **Challenge (v2) → "I'm not a robot" Checkbox**.

Di kolom **Domains, satu domain per baris.** Tekan `+` untuk tiap tambahan —
menulis `localhost, 127.0.0.1` dalam satu baris ditolak Google, karena koma
bukan pemisah dan ia membacanya sebagai satu nama domain:

```
localhost
127.0.0.1
cms.dwf-domino.org
```

Yang dicocokkan Google adalah **hostname di address bar**, bukan `APP_URL`.
`php artisan serve` menyajikan di `127.0.0.1`, jadi kalau kamu mengetik
`http://127.0.0.1:8000` maka `127.0.0.1` yang harus terdaftar; kalau mengetik
`http://localhost:8000`, `localhost` yang harus terdaftar. Port diabaikan.

**Untuk lokal, paling gampang: biarkan kedua kunci kosong.** Captcha-nya mati
total, tidak ada request apa pun ke Google, dan login tetap jalan — jadi tidak
perlu mendaftarkan domain lokal sama sekali. Daftarkan hanya domain produksi.

Kalau memang ingin melihat widget-nya jalan di lokal tanpa mendaftar apa pun,
pakai kunci uji resmi Google — keduanya **selalu lolos** dan menampilkan pita
peringatan, jadi jangan sekali-kali dipakai di produksi:

```
RECAPTCHA_SITE_KEY=6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI
RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe
```

Site key boleh publik (ia memang tercetak di HTML); **secret key jangan pernah
masuk repo**.

Lalu:

```bash
php artisan migrate --seed          # `--seed` mengisi data contoh
php artisan storage:link            # wajib: unggahan disajikan lewat symlink ini

composer dev                             # http://127.0.0.1:8000
```

**Satu perintah, satu terminal.** `composer dev` (sama dengan `php artisan dev`)
menyalakan tiga proses sekaligus dan menampilkannya sebagai tab: `server`
(`php artisan serve`), `vite` (`bun run dev`, HMR), dan `logs` (`php artisan
pail`). Ctrl+C mematikan ketiganya. Tidak perlu lagi membuka dua terminal untuk
`php artisan serve` dan `bun run dev` terpisah.

Masuk dengan email dan password yang barusan ditulis di `.env`.

Lalu buka **`/design-system`** (menu Developer → Design System). Di sana ada
contoh hidup tiap komponen — tombol, field, tabel, pagination, dialog, tipografi,
warna, ikon — lengkap dengan kode yang bisa disalin. Itu tempat memulai sebelum
menulis layar baru.

## Perintah

| Perintah | Guna |
|---|---|
| `composer dev` | **server + Vite + log dalam satu proses** — ini yang dipakai sehari-hari |
| `bun run build` | Build produksi |
| `bun run typecheck` | `vue-tsc --noEmit` — TypeScript strict |
| `php artisan test` | PHPUnit, jalan di `dwf_backoffice_testing` |
| `php artisan db:seed --class=AccessSeeder` | Sinkronkan peran & izin setelah menambah modul |
| `php artisan dwf:install` | **Pemasangan di database KOSONG** — izin, super admin pertama, baris SEO bawaan. Tanpa data contoh; itu bedanya dari `db:seed` |
| `php artisan dwf:2fa:reset {email}` | Reset 2FA sebuah akun — jalan keluar saat ponsel **dan** kode pemulihan hilang |
| `php artisan editor:prune --dry-run` | Sebutkan gambar editor yang tidak dirujuk HTML mana pun. Tanpa `--dry-run` ia membuangnya; dijadwalkan mingguan |
| `php artisan storage:link` | **Wajib tiap deploy dan tiap mesin.** Symlink-nya absolut dan tidak ikut git — lihat [docs/PRODUCTION.md](docs/PRODUCTION.md) §2 |
| `bun run dev` | Vite saja — jarang dipanggil langsung; `composer dev` yang memanggilnya |

Yang dijalankan `artisan dev` disetel di
[`app/Providers/AppServiceProvider.php`](app/Providers/AppServiceProvider.php).
Listener antrean sengaja dibuang: belum ada job yang di-dispatch, jadi tabnya
cuma menambah satu hal yang harus diabaikan. Dua pekerjaan TERJADWAL memang
ada (`editor:prune`, `activitylog:clean`) — keduanya butuh satu entri cron di
server, bukan worker antrean.

## Struktur

```
app/
├─ Console/Commands/        editor:prune — penyapu gambar editor (terjadwal)
├─ Http/Controllers/
│  ├─ Api/                  PublicController (baca) + SubmissionController (tulis)
│  └─ Cms/                  satu controller per modul backoffice
├─ Http/Middleware/         HandleInertiaRequests — props bersama
├─ Http/Resources/          PublicResource + turunannya (kontrak API publik)
└─ Support/
   ├─ Access.php            modul × aksi → izin. Menambah modul = satu baris
   ├─ Navigation.php        struktur sidebar — SATU sumber (lihat CONVENTIONS)
   ├─ Dashboard/DashboardData.php  query yang mengisi dashboard
   └─ Media/StoredFile.php         simpan/ganti/hapus berkas unggahan
resources/
├─ css/app.css              seluruh token desain (@theme)
└─ js/
   ├─ Components/           sistem desain: tombol, field, tabel, editor, dialog…
   ├─ Layouts/              AdminLayout (sidebar + topbar), AuthLayout
   └─ Pages/                satu folder per modul; DesignSystem/ referensinya
routes/
├─ web.php                  backoffice — tiap route dijaga `can:`
├─ api.php                  /api/v1 publik: 25 endpoint baca, 4 tulis
└─ console.php              dua pekerjaan terjadwal (butuh cron di server)
lang/
├─ en/backoffice.php        teks antarmuka — SUMBER (label wireframe berbahasa Inggris)
├─ id/backoffice.php        terjemahan Indonesia (bahasa bawaan)
└─ {en,id}/validation.php   pesan validasi Laravel
docs/
├─ DESIGN-TOKENS.md         token + sumber Figma tiap nilai
├─ PROGRESS.md              status modul, penyimpangan wireframe, pekerjaan terbuka
├─ API.md                   referensi /api/v1 — konvensi, tiap endpoint, bentuk galat
└─ PRODUCTION.md            yang gagal DIAM-DIAM di server — baca sebelum deploy
```

## Kenapa project terpisah dari situs publik

Alasannya di [`../landing-page-nuxt/docs/PRD.md`](../landing-page-nuxt/docs/PRD.md)
§7 (D70): audiens, autentikasi, jadwal rilis, dan permukaan serangannya berbeda,
jadi satu build yang memuat keduanya berarti satu bug di admin bisa menjatuhkan
situs publik.

Aturan kerjanya — dan hal yang mudah keliru — ada di
[docs/CONVENTIONS.md](docs/CONVENTIONS.md). `CLAUDE.md` dan `AGENTS.md` di akar
adalah symlink ke berkas itu: tiap asisten menemukan nama yang dicarinya, dan
isinya tetap satu supaya tidak ada dua salinan yang bisa berbeda pendapat.
