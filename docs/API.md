# API publik DWF — `/api/v1`

Satu-satunya sumber data untuk situs publik
[`../../landing-page-nuxt`](../../landing-page-nuxt). Kontrak bentuknya
ditetapkan lebih dulu di [`../../docs/PRD-API-PUBLIK.md`](../../docs/PRD-API-PUBLIK.md);
berkas ini **referensi apa yang benar-benar disajikan**, ditulis dari
`routes/api.php` dan diverifikasi dengan menembak tiap endpoint.

Diperbarui 2026-09-03 · 25 endpoint baca, 4 endpoint tulis. Setiap tipe di
`types.ts` situs publik sudah dicocokkan field per field dengan response nyata.

```
https://cms.dwf-domino.org/api/v1
```

Versinya di URL, bukan di header. Alasannya sederhana: sebuah URL bisa dibuka
di browser, ditempel di tiket, dan di-curl tanpa penjelasan tambahan.

---

## Konvensi

Sembilan aturan yang berlaku di **seluruh** endpoint. Masing-masing dijaga
`tests/Feature/Api/ApiConventionTest.php` — kalau endpoint berikutnya ditulis
dengan gaya sendiri, di sana ketahuannya.

### 1. Kunci selalu `camelCase`

`publishedAt`, `fileUrl`, `primaryEmail`. Di database ia `snake_case` karena
itu nama kolom; yang keluar tidak pernah begitu. `/settings` sempat jadi
pengecualian (kuncinya diambil apa adanya dari kolom) — satu response yang
beda gayanya memaksa pemakainya mengingat pengecualian, jadi itu diperbaiki.

**Kecuali kunci yang isinya DATA**, bukan nama field: `/seo` memetakan rute
(`"/federation-members"`) dan `/faqs` memakai slug kategori. Keduanya nilai
yang diketik orang, bukan skema.

### 2. Daftar adalah array telanjang

```json
[{ "id": "1", … }, { "id": "2", … }]
```

Bukan `{ "data": [...] }`. `client.ts` di situs publik membacanya begitu, dan
pembungkus bawaan Laravel menghasilkan `response.map is not a function` di
setiap halaman sekaligus.

Konsekuensi yang diterima sadar: **tidak ada metadata pagination.** Daftar di
sini pendek dan dibatasi `?limit=`; kalau suatu hari ada yang benar-benar
panjang, ia dapat endpointnya sendiri dengan bentuknya sendiri.

### 3. `id` selalu string

`"1"`, bukan `1`. Ia identitas, bukan bilangan — tidak pernah dijumlahkan, dan
tipe yang berubah saat sumbernya berpindah (auto-increment → UUID) adalah
perubahan yang menembus sampai ke JSX.

### 4. Field opsional DIHILANGKAN, bukan dikirim `null`

Halaman bisa memakai `??` tanpa memeriksa dua keadaan. `false`, `0`, dan `""`
TETAP dikirim — nol adalah jawaban sah untuk "berapa peserta".

### 5. Empat field sudah berupa teks siap tampil

`dateLabel` (`"Sep 18 - 21, 2026"`), `registrationLabel`
(`"Registration closes in 3 days"`), `location`, `fileSize` (`"2.3 MB"`).
Satuan dan formatnya milik API supaya semua halaman sepakat.

> **`registrationLabel` tidak boleh di-cache lebih dari sehari.** Ia menghitung
> selisih dari hari ini; response yang mengendap di edge cache akan terus
> menulis "3 days" sampai minggu berikutnya.

### 6. URL gambar dan berkas selalu absolut

Dibangun dari `APP_URL` (atau `MEDIA_URL` kalau media disajikan dari host
sendiri). Situs publik dan API tinggal di domain berbeda, jadi path relatif
akan menunjuk domain yang salah.

### 7. `?limit=` dijepit, tidak diturut mentah-mentah

Tiap daftar punya bawaan dan batas atasnya. Nol dan angka negatif dinaikkan ke
1 — bukan diloloskan: `limit(0)` mengembalikan daftar kosong, dan yang terbaca
di halaman adalah "tidak ada isinya", bukan "permintaan Anda salah".

### 8. Penyaring berdaftar tertutup MENOLAK nilai asing; penyaring teks bebas tidak

`?scope=`, `?tier=`, `?registration=`, `?placement=` punya daftar nilai yang
tetap. Salah ketik membalas **422** beserta daftar yang sah:

```json
{ "message": "The scope field must be one of: home, members." }
```

Sebelumnya `?scope=member` — kurang satu huruf — diam-diam membalas statistik
BERANDA: data yang masuk akal, dari daftar yang salah, tanpa satu pun tanda.

`?category=`, `?slug=`, dan `?q=` berisi teks yang diketik orang di CMS, jadi
nilai yang tidak cocok **wajar** mengembalikan daftar kosong. Itu jawaban,
bukan galat.

### 9. Baris yang belum tayang tidak pernah keluar

Setiap endpoint memakai scope `live()` modulnya: `status = published`, atau
`scheduled` yang `published_at`-nya sudah lewat. Tidak ada parameter untuk
memintanya — draft tidak bocor lewat query string.

### 10. Nama berbaris dua dikirim dengan `\n`; headline dikirim sebagai larik

`Champion.name` dan `BoardMember.name` berisi `"Marcus\nJohnson"` — satu nilai
yang kebetulan dibungkus dua baris, dan pemutusnya tipografi.
`home.closing.headline` berupa `["Bring Your Nation", "To The World Stage"]` —
dua baris berbobot sama yang memang dua hal. Bedanya disengaja; keduanya sudah
disepakati `types.ts` di situs publik.

---

## Galat

Satu bentuk untuk semuanya:

```json
{ "message": "Not found." }
```

| Status | Kapan | `message` |
|---|---|---|
| `404` | Slug/kunci tidak ada, atau rutenya memang tidak ada | `Not found.` |
| `405` | Verb salah | `Method not allowed.` |
| `422` | Validasi gagal (hanya endpoint tulis) | pesan field pertama, **plus `errors`** |
| `429` | Melewati throttle | `Too many requests.` |

`422` menambah satu kunci, mengikuti bentuk bawaan Laravel:

```json
{
  "message": "The email field must be a valid email address.",
  "errors": { "email": ["The email field must be a valid email address."] }
}
```

**Pesannya sengaja tidak menyebut apa pun tentang isi perut aplikasi.** Bawaan
Laravel membalas `No query results for model [App\Models\NewsArticle]` — bahkan
dengan `APP_DEBUG=false` — yang memberi tahu dunia nama kelas, namespace, dan
bahwa ini Laravel dengan route model binding. Penyeragamannya di
`bootstrap/app.php`, dan dibatasi `api/*`: backoffice tetap memakai halaman
galat Inertia-nya sendiri.

---

## Endpoint baca

Semuanya `GET`, semuanya tanpa autentikasi. Lihat [Akses](#akses) untuk
alasannya.

### Berita

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/news` | `category`, `featured`, `limit` (12, maks 48) | array `NewsArticle` |
| `/news/categories` | — | array **string**, bukan objek — ia daftar nama |
| `/news/{slug}` | — | satu `NewsArticle` + `body` (HTML) |

```json
{
  "id": "1",
  "slug": "madrid-singapore-mexico-city-host-the-2026-world-championship",
  "title": "Madrid, Singapore & Mexico City host the 2026 World Championship",
  "excerpt": "…",
  "category": "Tournament",
  "publishedAt": "2026-08-31T15:45:14+00:00",
  "isFeatured": true
}
```

### Dokumen

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/resources` | `category`, `limit` (24, maks 48) | array `ResourceDocument` |

Namanya `/resources`, bukan `/documents` — mengikuti `getResources()` dan tipe
`ResourceDocument` di situs publik, yang ditulis lebih dulu. Modul CMS-nya
bernama Documents; keduanya hal yang sama.

`fileUrl` menunjuk `/media/documents/{id}`, **bukan berkas statis**: dokumen
tunduk pada sakelar Visibility, dan berkas yang disajikan web server langsung
tidak pernah memeriksanya.

### Galeri

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/gallery` | `limit` (24, maks 60) | array `GalleryItem` |
| `/gallery/albums` | `slug` | array `GalleryAlbum` (album tanpa isi tayang dibuang) |

### FAQ

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/faqs` | `placement` = `home` \| `domino` \| `tournament` | array `Faq` |

Tanpa `placement`, seluruh daftar keluar — itu yang dibaca `/page/faq`, yang
mengelompokkannya sendiri per kategori. **Dengan** `placement`, urutannya milik
halaman itu (`faq_placements.position`), bukan urutan global.

Parameternya bernama `placement` dan **bukan `page`** dengan sengaja: `page`
adalah nama universal untuk "halaman ke berapa", dan orang pertama yang
menambahkan pagination akan menabraknya.

### Turnamen

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/tournaments` | `registration`, `limit` | array `Tournament` |
| `/tournaments/{slug}` | — | satu `TournamentDetail` |
| `/tournaments/highlighted` | — | satu `ShowcaseEvent`, atau `null` |
| `/tournaments/featured` | — | proyeksi 6 field untuk kartu hitung mundur, atau `null` |
| `/tournaments/showcase` | — | array 6 kartu showcase beranda |

**`highlighted` dan `featured` bukan sinonim**, dan ini yang paling sering
ditanyakan:

- `highlighted` — turnamen tayang terdekat dalam bentuk `ShowcaseEvent`, untuk
  hero halaman `/tournaments`. Bentuknya SAMA dengan `/tournaments/showcase`
  karena `Hero.vue` dan kartu showcase menggambar hal yang sama; sampai
  2026-09-03 ia mengirim `Tournament` penuh, dan komponennya tidak akan bisa
  membacanya.
- `featured` — turnamen sama, tapi hanya enam field yang dibutuhkan kartu
  hitung mundur beranda.

Keduanya memilih **turnamen tayang terdekat yang belum lewat**, bukan kolom
"unggulan" yang harus disetel tangan — kolom seperti itu basi tiap tengah
malam kecuali ada yang mengurusnya.

### Hasil

| Endpoint | Balasan |
|---|---|
| `/champions` | array `Champion` — aula juara, TIDAK diturunkan dari turnamen |
| `/olympic-results` | array `OlympicResult` |

### Federasi & orang

| Endpoint | Parameter | Balasan |
|---|---|---|
| `/members` | `tier` | array `MemberFederation` |
| `/stats` | `scope` = `home` \| `members` | array `{ id, label, value }` |
| `/board-members` | — | array `BoardMember` |
| `/sub-committees` | — | array `SubCommittee` |
| `/standing-committees` | — | array `StandingCommittee` (`remit` larik) |
| `/heritage-milestones` | — | array `HeritageMilestone` |
| `/partners` | — | array `Partner` |

`/members` mengirim `president`, `email`, dan `phone`. Itu **kontak resmi
sebuah organisasi yang memang diterbitkan federasinya** — ketiganya tercetak
di kartu detail halaman `/federation-members`, bukan data pribadi yang bocor.

### Halaman & pengaturan

| Endpoint | Balasan |
|---|---|
| `/legal/{key}` | `privacy-policy` \| `terms` \| `cookie-policy`. `sections[].description` HTML dasar |
| `/home` | `{ hero, closing }` — naskah beranda yang tidak dimiliki modul lain |
| `/settings` | objek kunci-nilai: kontak dan tautan sosial |
| `/seo` | `{ default, pages }` — halaman mencari rutenya lalu jatuh ke `default` |

---

## Endpoint tulis

Semuanya `POST`, semuanya membalas **`204 No Content`** tanpa isi. Tidak ada
satu pun formulir di situs publik yang menampilkan sesuatu dari responsnya, dan
mengirim balik baris yang baru dibuat berarti endpoint publik yang membocorkan
id berurutan tanpa satu pun alasan.

| Endpoint | Body | Throttle |
|---|---|---|
| `/contact` | `name`, `email`, `topic`, `message`, opsional `country`, `subject` | 5/menit |
| `/newsletter` | `email` | 5/menit |
| `/tournaments/{id}/subscribe` | `email` | 10/menit |
| `/integrity-reports` | `type`, `description` (min. 20 karakter) | 10/menit |

**Mendaftar dua kali membalas sukses, bukan 422.** Berlaku untuk `/newsletter`
dan `/subscribe`. Galat di sana akan memberi tahu siapa pun yang mengetik
sebuah alamat apakah alamat itu ada di daftar — sekaligus pesan galat untuk
sesuatu yang bukan kesalahan orangnya.

**Field `website` adalah jebakan bot.** Kalau terisi, permintaannya dibuang dan
tetap dibalas sukses. Situs publik harus merendernya sebagai input tersembunyi
(`aria-hidden`) agar jebakannya berfungsi; sampai itu ada, ia tidak menangkap
apa pun tapi juga tidak mengganggu.

`topic` di `/contact` menerima ejaan situs publik (`"Tournament support"`) dan
menyimpannya dalam ejaan CMS (`"Tournament Support"`).

Laporan integritas **anonim**: tidak ada nama, email, maupun alamat IP yang
disimpan. Halamannya menjanjikan kerahasiaan, dan alamat IP adalah identitas.

---

## Akses

**Tidak ada API key, dan itu keputusan — bukan kelalaian.** Setiap field yang
keluar dari endpoint baca berakhir tercetak di halaman yang bisa dibuka siapa
pun; tidak ada yang dilindungi sebuah kunci. Yang menjaganya:

- **CORS berdaftar putih** — `CORS_ALLOWED_ORIGINS`, sengaja **bukan** `*`.
  Kosong adalah bawaan yang benar: ia menghasilkan galat yang terlihat di
  konsol, sementara wildcard menghasilkan lubang yang tidak terlihat di mana
  pun.
- **Throttle di endpoint tulis** (tabel di atas). Endpoint baca belum
  di-throttle: dengan SSR seluruh trafik datang dari SATU alamat IP, jadi
  batas per-IP polos akan mencekik situs sendiri, bukan penyalin data.

**Kapan ini berubah:** portal pemain (data per-orang → Sanctum, token per
pengguna), konsumen pihak ketiga (key per konsumen, dengan kuota dan bisa
dicabut), atau begitu ada field yang tidak tercetak di halaman publik — saat
itu ia berhenti jadi API publik.

Tidak ada satu pun endpoint yang menerima `PUT`, `PATCH`, atau `DELETE`. Ada
tesnya.

---

## Menjalankan di lokal

```bash
composer dev                      # http://127.0.0.1:8000
curl http://127.0.0.1:8000/api/v1/tournaments | jq
```

Situs Nuxt menyambung lewat `NUXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1`.
Tanpa variabel itu ia tetap memakai data mock — itu disengaja, dan
`client.ts` yang memutuskannya.
