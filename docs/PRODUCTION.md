# Catatan produksi — DWF Backoffice

Yang harus diingat saat men-deploy dan menjalankan aplikasi ini di server.
Bukan panduan langkah-per-langkah — itu nanti di `deploy/`, yang belum ada.
Ini daftar hal yang **gagal diam-diam** kalau terlewat: tidak ada galat, tidak
ada log, dan yang terlihat cuma sesuatu yang "kadang tidak jalan".

Diperbarui 2026-09-03.

---

## 1. Satu entri cron, dua pekerjaan

```cron
* * * * * cd /path/ke/backend-cms && php artisan schedule:run >> /dev/null 2>&1
```

Tanpa baris ini, dua hal tumbuh tanpa batas dan **tidak ada satu pun layar yang
memperlihatkannya**:

| Pekerjaan | Jadwal | Yang dibereskan |
|---|---|---|
| `editor:prune --days=7` | Senin 03:10 | Gambar di `storage/app/public/editor` yang tidak disebut HTML mana pun |
| `activitylog:clean` | Harian 03:30 | Baris `activity_log` yang lebih tua dari `ACTIVITY_LOG_RETENTION_DAYS` |

Yang terlihat kalau cron-nya lupa dipasang: disk penuh, berbulan-bulan kemudian.

**`editor:prune` punya `--dry-run`.** Jalankan itu dulu di server baru sebelum
mempercayainya. Ambang `--days=7` jangan diturunkan ke 0: gambar diunggah saat
*disisipkan*, bukan saat formulirnya disimpan, jadi ada jendela nyata di mana
berkas sudah ada di disk tapi belum dirujuk — selama penulisnya masih mengetik.

---

## 2. `php artisan storage:link` — tiap deploy, tiap mesin

Seluruh unggahan (gambar berita, dokumen PDF, galeri, bendera federasi, foto
ofisial) disajikan lewat `public/storage`, sebuah **symlink absolut** ke
`storage/app/public`.

Konsekuensinya:

- Ia **tidak ikut git** (`.gitignore` baris 20), jadi deploy baru tidak
  membawanya.
- Ia menyimpan path **absolut**. Memindahkan folder aplikasi, mengganti nama
  direktori home, atau deploy bergaya symlink-release membuatnya menunjuk
  tempat yang tidak ada.

Gejalanya: seluruh gambar 404 sementara aplikasinya tampak sehat. Tidak ada
galat di log — berkasnya memang tidak ada di sana.

> Ini bukan kemungkinan teoretis. Symlink di mesin pengembangan repo ini mati
> persis begitu (menunjuk `/Users/monsky-macc/...` setelah direktori home
> berganti nama) dan baru ketahuan 2026-09-03.

Periksa dengan `test -e public/storage && ls public/storage`.

---

## 3. Media: dua disk, dan mana yang boleh disajikan langsung

Berkas unggahan dipisah berdasarkan **siapa yang boleh membacanya**, bukan
berdasarkan jenisnya.

| Disk | Isi | Cara keluar |
|---|---|---|
| `public` (`storage/app/public`) | Gambar berita, galeri, logo partner, bendera federasi, foto orang, gambar OG, gambar editor, foto ofisial turnamen | Symlink `public/storage`, disajikan web server langsung. Tanpa PHP — itu yang membuatnya cepat |
| `local` (`storage/app/private`) | **Dokumen (PDF)** | HANYA lewat `GET /media/documents/{id}`, yang memeriksa status dokumennya tiap permintaan |

**Yang `local` selesaikan:** sebelum 2026-09-03 dokumen tinggal di disk publik,
jadi mengubahnya jadi draft atau unpublished **tidak menurunkan berkasnya** —
yang diatur sakelar Visibility cuma daftarnya. Nama berkas memang acak, tapi
nama acak menahan TEBAKAN, bukan tautan yang sudah beredar.

**Memindahkan media ke folder dan host sendiri** (`MEDIA_ROOT` + `MEDIA_URL`):

```dotenv
MEDIA_ROOT=/var/www/dwf/media            # gambar publik
MEDIA_URL=https://media.dwf-domino.org   # dari mana browser mengambilnya
MEDIA_PRIVATE_ROOT=/var/www/dwf/private  # DOKUMEN — di luar MEDIA_ROOT
```

> **`MEDIA_PRIVATE_ROOT` wajib DI LUAR `MEDIA_ROOT`.** Kalau ia berada di
> dalamnya, symlink `public/storage` menjadikan setiap dokumen bisa diunduh
> siapa pun tanpa satu pun pemeriksaan status tayang — dan tidak ada gejalanya:
> aplikasi tetap jalan, layarnya tetap normal. `AppServiceProvider` karena itu
> **menolak boot** kalau keduanya bertumpuk. Lebih baik aplikasi tidak menyala
> daripada menyala dengan seluruh dokumennya terbuka.

**Memindahkan media keluar project memindahkan tanggung jawab BACKUP-nya juga.**
Kode ada di git; `media/` dan `private/` tidak ada di mana pun. Siapa pun yang
mem-backup "project" akan melewatkan keduanya, dan itu baru ketahuan pada hari
seseorang butuh memulihkannya. Masukkan kedua folder itu ke jadwal backup pada
hari yang sama Anda memindahkannya — bukan nanti.

Keduanya menjawab hal yang berbeda, dan cuma satu yang soal keamanan:

- **`MEDIA_ROOT`** — soal OPERASIONAL. Menaruh bytenya di luar direktori
  aplikasi membuat deploy bergaya rilis-simbolik tidak pernah menyentuhnya, dan
  backup media lepas dari backup kode. Tidak menambah keamanan.
- **`MEDIA_URL`** — soal KEAMANAN, tapi hanya kalau **hostname**-nya berbeda.
  Berkas unggahan yang disajikan dari origin yang sama dengan aplikasi berjalan
  di origin itu kalau browser terbujuk menafsirkannya sebagai HTML; dari
  `media.dwf-domino.org` ia berjalan di origin tanpa sesi dan tanpa hak apa
  pun. Itu alasan `raw.githubusercontent.com` ada.
  **Path berbeda di host yang sama (`/media` menggantikan `/storage`) tidak
  menambah apa pun** — origin-nya tetap sama.

Host medianya cukup nginx statis yang menunjuk `MEDIA_ROOT`. Aturan "tidak
mengeksekusi PHP" di bawah tetap berlaku di sana, dan justru di sana ia paling
penting: host itu tidak punya PHP sama sekali kalau disetel benar.

**Dokumen tidak ikut pindah.** Ia tunduk pada sakelar Visibility dan keluar
lewat `/media/documents/{id}`; host statis tidak bisa memeriksa status tayang.

**Cache: gambar publik boleh disimpan SELAMANYA, dokumen tidak boleh sama
sekali.**

Nama berkas unggahan adalah **40 karakter acak** (`hashName()`), dan mengganti
sebuah gambar SELALU menulis nama baru lalu membuang yang lama — lihat
`StoredFile::put()`. Artinya satu URL selalu berisi hal yang sama, selamanya.
Itu izin untuk bentuk cache paling kuat yang ada:

```nginx
location ^~ /storage/ {          # atau root host media terpisah
    location ~ \.php$ { return 403; }

    # URL-nya immutable: nama berkasnya acak dan tidak pernah dipakai ulang,
    # jadi tidak ada yang perlu divalidasi ulang. `immutable` menghentikan
    # revalidasi bahkan saat orang menekan Reload.
    add_header Cache-Control "public, max-age=31536000, immutable";
    add_header X-Content-Type-Options nosniff;
}
```

**Dokumen dikecualikan, dan bukan lewat nginx** — ia tidak lewat sana sama
sekali. `MediaController` mengirim `Cache-Control: private, no-store` sendiri,
disetel eksplisit dan ada tesnya. Alasannya: berkas yang tersimpan cache tetap
terunduh SETELAH dokumennya diturunkan, dan itu membatalkan seluruh guna
pemeriksaan statusnya.

**Satu risiko yang harus diketahui sebelum memasang CDN.** Immutable + setahun
aman untuk PENGGANTIAN (gambar baru = URL baru), tapi tidak untuk
PENGHAPUSAN: gambar yang dibuang karena alasan hukum bisa tetap tersaji dari
edge sampai TTL-nya habis. Kalau itu terjadi, purge CDN-nya — jangan
mengandalkan penghapusan di disk.

### Media di domain sendiri — yang harus disiapkan

Bentuk yang dituju:

```
https://cms.dwf-domino.org      aplikasi + API + unduhan dokumen
https://media.dwf-domino.org    gambar saja, nginx statis, tanpa PHP
```

1. **DNS** — A/CNAME `media.dwf-domino.org` ke server yang sama tidak apa-apa;
   yang memisahkannya adalah origin-nya, bukan mesinnya.
2. **Sertifikat TLS** untuk hostname itu. Gambar yang disajikan lewat HTTP di
   halaman HTTPS diblokir browser sebagai mixed content — dan diblokir DIAM,
   yang terbaca seperti gambarnya hilang.
3. **Blok nginx sendiri**, root ke `MEDIA_ROOT`, tanpa PHP sama sekali:

```nginx
server {
    server_name media.dwf-domino.org;
    root /var/www/dwf/media;

    # Tidak ada `location ~ \.php$ { fastcgi_pass … }` di blok ini — dan itu
    # inti pemisahannya. Host ini tidak punya PHP untuk dijalankan.
    location ~ \.php$ { return 403; }

    location / {
        add_header Cache-Control "public, max-age=31536000, immutable";
        add_header X-Content-Type-Options nosniff;
        try_files $uri =404;
    }
}
```

4. **`.env` aplikasi**: `MEDIA_URL=https://media.dwf-domino.org`. Sudah diuji —
   API langsung mengirim `https://media.dwf-domino.org/tournaments/….webp`.
5. **CORS tidak perlu ditambah.** `<img src>` tidak menuntut CORS. Yang
   menuntutnya cuma font dan `fetch()`, dan tidak satu pun dari media ini
   diambil begitu.

**Dokumen TIDAK ikut pindah**, dan itu terbukti di response: `fileUrl` tetap
menunjuk `https://cms.dwf-domino.org/media/documents/{id}`. Situs karena itu
akan punya dua asal berkas — gambar dari host media, dokumen dari aplikasi —
dan memang harus begitu: host statis tidak bisa memeriksa status tayang.

> **Ranjau untuk nanti: `@nuxt/image`.** Sekarang situs publik memakai
> `provider: "none"` (CPU server produksi di bawah x86-64-v2, sharp menolak
> jalan), jadi `<NuxtImg>` cuma mencetak `<img src>` apa adanya dan host media
> mana pun bekerja. **Begitu IPX dinyalakan kembali**, ia akan MENGAMBIL gambar
> dari host media untuk diubah ukurannya — dan `@nuxt/image` menolak host yang
> tidak terdaftar sebagai penjagaan SSRF. `media.dwf-domino.org` harus masuk
> `image.domains` di `nuxt.config.ts`, atau setiap gambar di situs 403
> sekaligus.

**Konsekuensi untuk konfigurasi server:**

- `storage/app/private` **tidak boleh** bisa dijangkau lewat URL. Jangan pernah
  men-symlink-nya ke `public/`, dan pastikan `root` nginx menunjuk `public/`
  saja.
- `local` disetel `'serve' => false` di `config/filesystems.php`. Bawaan Laravel
  `true` mendaftarkan sepasang route `/storage/{path}` (baca DAN tulis) untuk
  disk itu. Keduanya menuntut URL bertanda tangan jadi bukan lubang, tapi
  aplikasi ini tidak pernah menerbitkan tanda tangan seperti itu — yang ada cuma
  jalan masuk kedua ke berkas yang justru dipindahkan ke sana supaya punya SATU
  pintu berpenjaga. Jangan dinyalakan lagi.
- **Direktori unggahan tidak boleh mengeksekusi PHP.** Berlaku untuk
  `public/storage` di nginx:

  Contohnya di blok nginx di atas. Validasi sudah menolak apa pun selain WebP dan PDF (`mimes:` membaca mime
  asli lewat fileinfo, jadi `.php` yang diganti nama tetap ditolak), tapi aturan
  ini yang menahan kalau suatu saat ada modul baru yang lupa memvalidasi.

**Kalau nanti pindah ke object storage (S3/R2/Spaces):** yang berubah cuma
`config/filesystems.php` dan `.env` — `StoredFile` menerima nama disk sebagai
parameter, dan `MediaController` tetap jadi pintunya. Untuk berkas privat di
sana, `temporaryUrl()` bisa dipakai, tapi ingat bahwa tanda tangan yang
diterbitkan saat dokumen masih tayang **tetap sah setelah dokumennya
diturunkan** sampai kedaluwarsanya — jadi kalau itu dipakai, umurnya harus
pendek.

---

## 4. `.env` — yang wajib, dan yang gagal diam-diam kalau salah

### Wajib diisi, aplikasi menolak jalan tanpanya

| Kunci | Kenapa |
|---|---|
| `DWF_ADMIN_EMAIL`, `DWF_ADMIN_PASSWORD` | `DatabaseSeeder` menolak jalan kalau kosong. Kredensial admin tidak pernah ditulis di kode |
| `APP_KEY` | seperti Laravel mana pun |

### Wajib diisi, dan **kalau kosong gagalnya tidak kelihatan di server**

| Kunci | Kalau salah |
|---|---|
| `APP_URL` | API publik mengirim **URL gambar absolut** (§5.2 kontrak API). Salah nilainya = tiap gambar di situs publik menunjuk domain yang salah, sementara backoffice tetap normal |
| `CORS_ALLOWED_ORIGINS` | Daftar domain situs publik, dipisah koma. **Kosong adalah bawaan yang benar** dan menghasilkan galat CORS yang terlihat di konsol; wildcard `*` menghasilkan lubang yang tidak terlihat di mana pun. Jangan pakai `*` |
| `MAIL_*` | Undangan admin dikirim **sinkron** (`Mail::to()->send()`, bukan antrean). SMTP yang lambat memperlambat request-nya; SMTP yang mati membuat undangan gagal terkirim — tapi akunnya tetap dibuat, dan layarnya memberi tahu bahwa tautannya perlu dikirim ulang. Tombolnya sudah ada |

### Sakelar yang perilakunya sengaja "mati kalau kosong"

| Kunci | Catatan |
|---|---|
| `RECAPTCHA_SITE_KEY` + `RECAPTCHA_SECRET_KEY` | Aktif **hanya kalau keduanya terisi**. Tidak ada flag `enabled` terpisah — sakelar yang menyala sementara kuncinya kosong berarti login yang menolak semua orang. Verifikasinya sengaja **gagal-terbuka** saat Google tidak bisa dihubungi |
| `DWF_TWO_FACTOR` (bawaan `true`) | Mematikannya mematikan 2FA untuk seluruh aplikasi |
| `DWF_LOCALE_SWITCHER` (bawaan `false`) | Saat mati, tombol bahasa hilang, `/locale` membalas 404, dan `users.locale` yang tersimpan **diabaikan** — kalau tidak, mematikannya akan mengunci siapa pun yang pernah memilih bahasa lain |
| `ACTIVITY_LOG_RETENTION_DAYS` (bawaan `365`) | Berapa lama jejak audit disimpan. Ini **keputusan kebijakan**, bukan kenyamanan: yang menentukannya adalah berapa lama federasi perlu bisa menjawab "siapa yang mengubah ini" |

### Yang berbeda dari bawaan `.env.example`

`.env.example` disetel untuk pengembangan: `APP_ENV=local`, `APP_DEBUG=true`,
`MAIL_MAILER=log`. Ketiganya harus diubah di produksi — `APP_DEBUG=true` di
server mencetak isi `.env` ke halaman galat.

---

## 5. Perintah yang TIDAK BOLEH dijalankan di database produksi

### `migrate:fresh`, `migrate:refresh`, `db:wipe`

Selain menghapus semua data, ada akibat kedua yang tidak langsung terpikir:
**pendaftaran 2FA ikut hilang, dan akunnya kembali MENUNTUT 2FA.**
`two_factor_secret` dan `two_factor_confirmed_at` terhapus bersama barisnya,
sementara kolom `two_factor_enabled` bawaannya `true`. Yang terlihat
pemakainya: "kenapa saya harus setup 2FA lagi?"

Tes memakai database terpisah (`dwf_backoffice_testing`), jadi
`php artisan test` aman.

### `db:seed --class=FrontendContentSeeder`

Ia **data contoh**, disalin dari situs publik. Dua methodnya menghapus lebih
dulu supaya hasilnya deterministik:

- `FederationStat::where('scope', …)->delete()` — seluruh statistik home dan members
- `FaqPlacement::whereIn('page', Faq::PAGES)->delete()` — seluruh penempatan FAQ di Home, Domino, dan Tournament

Di database yang sudah diisi redaksi, itu membuang pekerjaan orang. Jalankan
hanya sekali, di database yang masih kosong.

### `db:seed` polos di mesin yang bukan `local`

Seeder daftar IP menanam baris **nonaktif** justru karena ini: aturan
"All Admins" yang aktif akan menegakkan dirinya pada request berikutnya, dan
layar yang bisa membatalkannya ada di balik pintu yang baru saja terkunci.

---

## 6. Database: memeriksa yang sudah ada, lalu menyiapkannya

### Apakah PostgreSQL sudah terpasang?

```bash
psql --version                 # ada? versinya berapa?
pg_isready                     # jalan? "accepting connections" yang dicari
sudo systemctl status postgresql   # kalau `pg_isready` bilang tidak
```

Kalau `psql` tidak ada sama sekali, belum terpasang. Kalau ada tapi
`pg_isready` menolak, ia terpasang tapi mati.

**Versi minimum: PostgreSQL 13.** Repo ini dikembangkan di 17 dan tidak memakai
fitur yang lebih baru dari 13; yang dipakainya dan sering tidak ada di versi
sangat lama adalah `ilike` (semua penyaring pencarian) dan tipe `json`.

Periksa juga siapa yang sudah memakai server itu — satu instance bisa menampung
banyak database, dan menimpanya adalah cara paling cepat menghapus milik orang
lain:

```bash
sudo -u postgres psql -c "\l"     # daftar database beserta pemilik & encoding
sudo -u postgres psql -c "\du"    # daftar role
```

### Kalau belum ada

```bash
sudo apt install postgresql          # Debian/Ubuntu
sudo systemctl enable --now postgresql
```

### Membuat database dan penggunanya

```bash
sudo -u postgres createuser --pwprompt dwf
sudo -u postgres createdb --owner=dwf --encoding=UTF8 dwf_backoffice
```

**`--encoding=UTF8` bukan formalitas.** Isi situs ini memuat em dash, tanda
kutip melengkung, dan teks Indonesia; database berencoding lain menyimpannya
sebagai sampah yang baru terlihat setelah datanya banyak.

**`--owner=dwf`** supaya migrasi tidak perlu dijalankan sebagai superuser.
Aplikasi tidak butuh hak lebih dari memiliki databasenya sendiri.

Uji sambungannya sebagai pengguna itu, bukan sebagai `postgres`:

```bash
psql "postgresql://dwf:SANDI@127.0.0.1:5432/dwf_backoffice" -c "select version();"
```

### Menyalakan aplikasinya di atas database kosong

```bash
php artisan migrate --force
php artisan dwf:install
```

`dwf:install` yang menyiapkan yang WAJIB ada sebelum satu orang pun bisa masuk:
peran dan izin, akun super admin pertama dari `.env`, dan baris SEO bawaan.

> **Jangan jalankan `php artisan db:seed` di produksi.** Ia memang membuat akun
> admin — tapi sekaligus menanam berita contoh, turnamen contoh, dokumen, pesan
> kontak, dan aturan daftar IP. Itu bukan awal yang bersih melainkan pekerjaan
> menghapus, dan menghapusnya satu per satu lewat layar adalah cara paling
> mudah keliru untuk memulai. `dwf:install` aman dijalankan berulang dan
> **tidak pernah menimpa sandi admin yang sudah ada** — sandi di `.env` server
> bisa jauh lebih tua daripada yang benar-benar dipakai orangnya.

### Yang harus diisi lewat backoffice sesudahnya

Database yang baru dipasang berisi struktur, bukan isi. Empat layar ini yang
menentukan apakah situs publik tampil utuh:

| Layar | Kalau kosong |
|---|---|
| Contact & Social | Kaki halaman tanpa alamat dan tanpa tautan sosial |
| Legal Pages | `/page/terms`, `/page/privacy-policy`, `/page/cookie-policy` kosong |
| Home Page | Hero dan ajakan penutup memakai naskah bawaan dari kode |
| SEO & Social | Judul halaman memakai bawaan kode; tautan yang dibagikan tanpa gambar |

### Cadangan

Yang harus masuk jadwal, dan keduanya sering terlewat karena bukan berkas kode:

```bash
pg_dump -Fc "postgresql://dwf:SANDI@127.0.0.1/dwf_backoffice" -f dwf-$(date +%F).dump
```

…dan **kedua folder media** (§3). Kode ada di git; database dan media tidak ada
di mana pun.

---

## 7. nginx

Berkas confignya **di luar repo** (`deploy/` di-gitignore — isinya path dan
hostname mesin sungguhan). Yang ditulis di sini alasannya, supaya bisa disusun
ulang dari nol kalau berkasnya hilang.

Dua host, satu aplikasi, satu `root`:

| Host | Melayani |
|---|---|
| `fed-bo.pborado.com` | Backoffice — semuanya |
| `fed-api.pborado.com` | **Hanya `/api`**, sisanya 404 |

Kerangka host API — bagian yang membuat pembatasannya benar-benar bekerja:

```nginx
server {
    server_name fed-api.pborado.com;
    root /home/oredo/dev_html/dwf-backend/public;
    client_max_body_size 1M;

    location / { return 404; }

    location ^~ /api/ {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        internal;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;   # cocokkan: ls /run/php/
    }
}
```

**`internal` bukan pengerasan tambahan, ia syaratnya.** Tanpa baris itu,
`location / { return 404; }` bisa dilewati begitu saja dengan meminta
`/index.php?…`, dan seluruh backoffice terbuka lewat nama yang seharusnya cuma
melayani API.

Host backoffice memakai `try_files $uri $uri/ /index.php?$query_string` biasa,
ditambah blok `^~ /storage/` untuk media (cache `immutable`, dan
`location ~ \.php$ { return 403; }` di dalamnya).

Tiga hal yang paling mudah keliru kalau ditulis ulang dari ingatan:

- **`root` menunjuk `public/`, bukan akar project.** Menunjuk akar berarti
  `.env`, `storage/`, dan `vendor/` bisa diunduh siapa pun — dan aplikasinya
  tetap jalan, jadi tidak ada satu pun galat yang memberi tahu.
- **`client_max_body_size` di host backoffice harus di atas 10 MB.** Bawaan
  nginx 1 MB, dan itu menolak unggahan dokumen sebelum Laravel melihatnya; yang
  terlihat pemakainya cuma "413" tanpa penjelasan dari layar mana pun. PHP juga:
  `upload_max_filesize` ≥ 10M dan `post_max_size` ≥ 12M.
- **CORS TIDAK disetel di nginx.** Laravel yang mengaturnya lewat
  `CORS_ALLOWED_ORIGINS`; menambahkannya di kedua tempat menghasilkan dua header
  `Access-Control-Allow-Origin`, yang ditolak browser dengan pesan yang menunjuk
  ke arah salah.

`APP_URL` menunjuk host **backoffice**, bukan host API — ia yang membangun URL
gambar dan unduhan dokumen di response API, dan juga redirect login, tautan
undangan, serta reset sandi. Menyetelnya ke host API demi kerapian membuat
ketiganya menunjuk host yang membalas 404.

Membuktikan pemisahannya bekerja:

```bash
curl -si https://fed-api.pborado.com/api/v1/news | head -1   # 200
curl -si https://fed-api.pborado.com/login       | head -1   # 404  ← yang penting
curl -si https://fed-api.pborado.com/index.php   | head -1   # 404  ← `internal`
curl -si https://fed-bo.pborado.com/.env         | head -1   # 403
```

Baris kedua dan ketiga yang membuktikannya. Kalau `/login` di host API membalas
200, `internal` tidak terpasang.

TLS lewat `certbot --nginx -d fed-bo.pborado.com -d fed-api.pborado.com`,
dijalankan SESUDAH blok HTTP-nya hidup — certbot yang menulis bagian TLS-nya,
dan dua sumber untuk hal yang sama adalah dua tempat yang bisa berbeda pendapat.

---

## 8. Yang HARUS dijalankan tiap deploy

```bash
php artisan migrate --force
php artisan db:seed --class=AccessSeeder   # kalau ada modul/izin baru
                                           # (pemasangan PERTAMA: `dwf:install`, lihat §6)
php artisan storage:link                    # lihat §2
php artisan config:cache && php artisan route:cache && php artisan view:cache
bun install && bun run build
```

**`AccessSeeder` aman dijalankan berulang** dan wajib dijalankan setiap kali ada
modul baru — izin dibangkitkan dari `App\Support\Access::MODULES`. Kalau lupa,
modul barunya tidak terjangkau siapa pun kecuali `super-admin` (yang melewati
semuanya lewat `Gate::before`), dan tidak ada galat yang memberi tahu.

**Mengganti nama izin: RENAME barisnya, jangan buat ulang.**
`role_has_permissions` menunjuk `permissions.id`, bukan namanya — membuat baris
baru berarti tiap peran kustom kehilangan akses ke modul itu sampai seseorang
mencentangnya lagi satu per satu. Polanya di
`2026_09_02_140000_rename_press_releases_to_documents`.

---

## 9. Daftar IP — cara mengunci diri sendiri

`EnforceIpWhitelist` meloloskan siapa pun yang **tidak disasar** aturan mana
pun, jadi tabel kosong berarti tidak ada yang dibatasi. Yang berbahaya adalah
aturan pertama yang aktif: ia berlaku pada request berikutnya, termasuk request
Anda sendiri.

- **Super admin TIDAK dikecualikan**, dan itu disengaja.
- Yang menahan orang mengusir dirinya sendiri adalah
  `IpWhitelist::wouldLockOut()`, dipanggil di store, update, sakelar status,
  DAN hapus.
- Kedaluwarsa dihitung **saat request**, bukan lewat job — jadi Validity dan
  Status memang dua kolom berbeda: sebuah aturan bisa aktif DAN sudah lewat
  tanggalnya sekaligus.

Kalau terlanjur terkunci, satu-satunya jalan adalah menyunting tabel
`ip_whitelist_rules` langsung di database.

---

## 10. Skala: satu server vs beberapa

Belum diuji di lebih dari satu server. Yang perlu diperiksa lebih dulu kalau
nanti ditambah:

- **Throttle endpoint publik** (`/api/v1/contact`, `/newsletter`, dsb) memakai
  cache store. Dengan `CACHE_STORE=database` ia sudah terbagi; dengan cache
  per-server, tiap server punya hitungannya sendiri dan batasnya jadi berlipat.
- **Sesi** memakai `SESSION_DRIVER=database` — sudah terbagi.
- Kedua jadwal sudah memakai `onOneServer()` dan `withoutOverlapping()`.
- Unggahan disimpan di disk **lokal** (`storage/app/public` dan
  `storage/app/private`). Dua server berarti berkas yang diunggah ke server A
  tidak ada di server B. Butuh disk bersama (S3/R2) sebelum server kedua
  ditambahkan — lihat §3 untuk apa yang berubah.

---

## 11. Situs publik menunggu satu nilai

`landing-page-nuxt` membaca API ini lewat `NUXT_PUBLIC_API_BASE_URL`. Nilainya
harus menunjuk `https://domain-backoffice/api/v1`, dan domain situs publiknya
harus ada di `CORS_ALLOWED_ORIGINS` di sisi sini. Keduanya harus benar; salah
satu saja membuat situs publik jatuh ke mock atau ke galat CORS.

**`registrationLabel` dari API tidak boleh di-cache lebih dari sehari.** Ia
teks siap tampil ("in 3 days"), bukan timestamp — halaman yang menyimpannya di
edge cache akan terus menulis "3 days" sampai minggu berikutnya.
