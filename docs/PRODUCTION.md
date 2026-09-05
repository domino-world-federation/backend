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

Susunan yang dipakai:

```
/home/oredo/dwf-media/
├── public/     ← disajikan fed-pub-media.pborado.com, nginx statis
└── private/    ← dokumen; TIDAK pernah disajikan nginx
```

```dotenv
MEDIA_ROOT=/home/oredo/dwf-media/public
MEDIA_URL=https://fed-pub-media.pborado.com
MEDIA_PRIVATE_ROOT=/home/oredo/dwf-media/private
```

**Tidak ada FTP dan tidak ada langkah pindah.** Aplikasinya menulis LANGSUNG ke
folder itu — `MEDIA_ROOT` adalah root disk `public` Laravel, jadi unggahan dari
backoffice mendarat di sana pada saat disimpan. Yang perlu dipastikan cuma
izinnya: PHP-FPM (`www-data`) harus bisa menulis ke **keduanya**.

```bash
sudo mkdir -p /home/oredo/dwf-media/public /home/oredo/dwf-media/private
sudo chown -R oredo:www-data /home/oredo/dwf-media
sudo find /home/oredo/dwf-media -type d -exec chmod 2775 {} \;
```

`private/` **bersebelahan** dengan `public/`, bukan di dalamnya. Kalau
tertukar, aplikasi menolak boot — lihat peringatan di bawah.

`php artisan storage:link` tidak lagi diperlukan dalam susunan ini: symlink
`public/storage` hanya berguna kalau media disajikan dari host aplikasi, dan di
sini ia disajikan host sendiri.

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
https://fed-bo.pborado.com         aplikasi + unduhan dokumen
https://fed-api.pborado.com        API publik saja (/api)
https://fed-pub-media.pborado.com  gambar saja, nginx statis, tanpa PHP
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

4. **`.env` aplikasi**: `MEDIA_URL=https://fed-pub-media.pborado.com`. Sudah
   diuji — API langsung mengirim
   `https://fed-pub-media.pborado.com/tournaments/….webp`.

   Dan `/storage/` di host backoffice diubah jadi `404`: kalau ia tetap
   melayani, berkas unggahan bisa dicapai dari DUA origin, dan yang satu origin
   aplikasi — yang justru dipisahkan supaya berkas orang tidak pernah berjalan
   di sana.
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
| `MAIL_*` | Undangan admin dikirim **sinkron** (`Mail::to()->send()`, bukan antrean). SMTP yang lambat memperlambat request-nya; SMTP yang mati membuat undangan gagal terkirim — tapi akunnya tetap dibuat, dan layarnya memberi tahu bahwa tautannya perlu dikirim ulang. Tombolnya sudah ada. **Penyetelan lengkapnya di §15** — termasuk kenapa `MAIL_MAILER=log` adalah kegagalan yang terlihat seperti keberhasilan |

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
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;   # cocokkan: ls /run/php/
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

## 8. PHP

### PHP: versi dan ekstensi

**Wajib PHP 8.3+.** `composer.json` menuntut `"php": "^8.3"` dan Laravel 13 di
atasnya. PHP 8.2 bukan "agak lama" — `composer install` menolak jalan di sana
kecuali dipaksa, dan yang dipaksa akan gagal saat dijalankan, bukan saat
dipasang.

Ubuntu bawaan sering hanya sampai 8.1 atau 8.2, jadi PPA-nya yang dipakai:

Yang dipakai server ini: **PHP 8.4**.

```bash
sudo apt install php8.4-fpm php8.4-pgsql php8.4-mbstring php8.4-xml \
                 php8.4-curl php8.4-zip php8.4-gd php8.4-bcmath php8.4-intl
sudo systemctl enable --now php8.4-fpm

ls /run/php/          # WAJIB memunculkan php8.4-fpm.sock
```

**PHP 8.4 terpasang tidak sama dengan php8.4-fpm berjalan.** Paket CLI dan
paket FPM terpisah, dan `php -v` cuma memberi tahu soal yang pertama. Yang
menentukan apa yang melayani web adalah soket yang ada di `/run/php/` — kalau
di sana hanya ada `php8.2-fpm.sock`, maka 8.2 yang melayani, seberapa pun
barunya versi CLI-nya.

**`php8.4-gd` bukan opsional.** Aturan validasi `dimensions:` di Add News
(minimal 1920×800, rasio 12:5) membacanya lewat GD; tanpa ekstensi itu, tiap
unggahan gambar ditolak dengan pesan yang menyebut ukuran, bukan menyebut
ekstensi yang hilang.

Beberapa versi PHP bisa hidup berdampingan. Yang menentukan mana yang dipakai
adalah `fastcgi_pass` di config nginx — bukan `php -v`, yang menunjukkan versi
CLI dan bisa berbeda dari versi FPM.

### Setelan PHP yang harus diubah

Bawaan PHP disetel untuk shared hosting tahun 2010. Empat di antaranya **gagal
diam-diam** di aplikasi ini — tidak ada galat, tidak ada log, cuma data yang
tiba tidak utuh.

```ini
; /etc/php/8.4/fpm/php.ini
```

#### Yang gagal DIAM-DIAM kalau dibiarkan

| Setelan | Bawaan | Pakai | Kenapa |
|---|---|---|---|
| `max_input_vars` | 1000 | **3000** | Satu formulir Add Tournament bisa mengirim ~1086 input |
| `max_file_uploads` | 20 | **60** | Satu turnamen bisa mengirim 51 berkas |
| `upload_max_filesize` | 2M | **10M** | Batas dokumen di `config/dwf.php` 10 MB |
| `post_max_size` | 8M | **12M** | Harus di atas `upload_max_filesize` + field lain |

**`max_input_vars` yang paling berbahaya.** Formulir turnamen mengizinkan 50
ofisial × 5 field, 200 baris jadwal × 4 field, plus ~36 field dasar — 1086 di
kasus terburuk, melewati bawaan 1000. Dan PHP **tidak menolak**: ia memotong
array POST-nya diam-diam. Yang terlihat orangnya adalah turnamen yang tersimpan
dengan sebagian jadwalnya hilang, tanpa satu pun pesan, dan tanpa cara menebak
kenapa.

**`max_file_uploads`** persis sama sifatnya: 50 foto ofisial + gambar hero = 51
berkas, dan berkas ke-21 dan seterusnya dibuang tanpa suara.

#### Kecepatan

```ini
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0

realpath_cache_size=4096K
realpath_cache_ttl=600

memory_limit=256M
```

`max_accelerated_files=20000` karena `app/` + `vendor/` berisi **9.148 berkas
PHP**. Bawaannya 10000 — cukup hari ini, dan diam-diam berhenti cukup begitu
ada beberapa paket lagi. Yang terjadi saat penuh bukan galat melainkan cache
yang berhenti menerima berkas baru, jadi sebagian aplikasi cepat dan sisanya
tidak.

**`validate_timestamps=0` adalah pertukaran, bukan kemenangan gratis.** Dengan
itu PHP berhenti memeriksa apakah berkasnya berubah — jadi **kode baru tidak
akan terpakai sampai FPM di-restart**. Itu harus masuk skrip deploy:

```bash
sudo systemctl reload php8.4-fpm
```

Lupa satu kali berarti deploy yang "berhasil" tapi situsnya tetap menjalankan
versi lama, dan tidak ada satu pun tanda yang memberi tahu. Kalau belum ada
skrip deploy yang bisa dipercaya, biarkan `validate_timestamps=1` dulu.

**JIT sengaja tidak dinyalakan.** Ia menolong perhitungan numerik yang panjang,
bukan aplikasi web yang menghabiskan waktunya di I/O dan database. Untuk beban
seperti ini ia sering netral dan kadang merugikan.

#### Keamanan

```ini
expose_php=Off
display_errors=Off
display_startup_errors=Off
log_errors=On
```

`display_errors=On` di produksi mencetak jejak tumpukan — berikut isi variabel
di dalamnya — ke halaman yang dilihat pengunjung.

`cgi.fix_pathinfo=0` **tidak perlu** di sini, walau hampir setiap panduan
menyebutnya. Yang dilindunginya adalah nginx yang meneruskan sembarang path ke
PHP; config di §7 sudah menutup itu dari arah lain lewat `try_files` dan
`internal`.

#### Pool FPM

```ini
; /etc/php/8.4/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 20        ; RAM tersedia ÷ ~50 MB per proses
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 500       ; daur ulang worker, menahan kebocoran memori
```

`pm.max_children` adalah satu-satunya angka yang harus dihitung, bukan disalin:
kalikan dengan ~50 MB dan hasilnya tidak boleh melebihi RAM yang benar-benar
bebas. Terlalu besar berarti server mulai swap di bawah beban — yang jauh lebih
buruk daripada permintaan yang antre.

Sesudah semuanya:

```bash
sudo systemctl restart php8.4-fpm
php -i | grep -E 'max_input_vars|max_file_uploads|upload_max_filesize|opcache.enable'
```

Periksa lewat `php -i` **milik FPM**, bukan CLI — keduanya membaca `php.ini`
yang berbeda (`/etc/php/8.4/fpm/` vs `/etc/php/8.4/cli/`). Cara paling pasti:
buat `phpinfo()` sementara, lihat lewat browser, lalu hapus.

---

## 9. Kalau situsnya tidak mau menyala

### `502 Bad Gateway` di host backoffice

502 berarti nginx SAMPAI ke PHP-FPM tapi tidak mendapat jawaban yang sah. Ia
hampir tidak pernah berarti aplikasinya salah — aplikasi yang salah membalas
500. Yang salah biasanya sambungan ke FPM-nya.

```bash
sudo tail -50 /var/log/nginx/fed-bo.error.log   # atau /var/log/nginx/error.log
```

Baris terakhirnya yang menjawab, dan ketiganya berbeda sebab:

| Pesan di log | Sebabnya |
|---|---|
| `connect() to unix:/run/php/phpX.Y-fpm.sock failed (2: No such file or directory)` | Soketnya tidak ada — versi di config tidak sama dengan yang terpasang |
| `... failed (13: Permission denied)` | Soketnya ada, tapi pengguna nginx tidak boleh membacanya |
| `upstream prematurely closed connection` | PHP mati di tengah jalan — lihat log FPM, bukan log nginx |

**Yang pertama paling sering**, dan cara memastikannya satu perintah:

```bash
ls /run/php/                        # soket yang BENAR-BENAR ada
sudo systemctl status php8.4-fpm
```

Cocokkan hasilnya dengan `fastcgi_pass` di KEDUA berkas config — host
backoffice dan host API. Mengganti satu saja meninggalkan yang lain tetap 502.

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Aplikasinya sendiri sehat?

Melewati nginx sama sekali — kalau ini gagal, masalahnya bukan di nginx:

```bash
cd /home/oredo/dev_html/dwf-backend
php artisan about --only=environment
php artisan db:show | head -5
```

### Mixed content: "Network error" saat menekan Login

Gejalanya di konsol browser:

```
Mixed Content: The page at 'https://fed-bo.pborado.com/login' was loaded over
HTTPS, but requested an insecure XMLHttpRequest endpoint
'http://fed-bo.pborado.com/two-factor/setup'. This request has been blocked.
```

**Aplikasi tidak tahu permintaannya lewat TLS.** nginx yang mengakhiri TLS lalu
bicara ke PHP-FPM lewat soket biasa; kalau skemanya tidak ikut diteruskan,
Laravel mengira semuanya http.

Dan itu tidak kelihatan sampai ada REDIRECT. Halaman biasa memakai path relatif
dan tetap benar — yang patah `redirect()->route(...)`, yang mengirim
`Location: http://…`. Browser memblokirnya, dan yang tersisa di layar cuma
"Network error" tanpa satu kata pun tentang skema.

Dua hal, dan keduanya sebaiknya ada:

**1. nginx meneruskan skemanya** — perbaikan yang sebenarnya. Di dalam blok
`location ~ \.php$`:

```nginx
fastcgi_param HTTPS $https if_not_empty;
```

`if_not_empty` yang membuatnya aman dipasang di blok port 80 sekaligus:
`$https` kosong di sana, jadi paramnya tidak dikirim.

**2. `APP_URL` memakai `https://`** — jaring pengamannya. `AppServiceProvider`
memanggil `URL::forceScheme('https')` kalau `APP_URL` diawali `https://`, jadi
URL yang dibangun tetap benar bahkan kalau config server salah. Di lokal
`APP_URL` http, jadi ini tidak menyala.

```dotenv
APP_URL=https://fed-bo.pborado.com
```

Sesudah mengubah `.env`: `php artisan config:clear` (atau `config:cache` lagi) —
tanpa itu nilai lamanya masih dipakai.

**Kalau nanti ada CDN atau load balancer di depan**, skemanya datang sebagai
header `X-Forwarded-Proto`, bukan `fastcgi_param`. `bootstrap/app.php` sudah
memanggil `trustProxies(at: \'*\')` untuk itu — sah karena PHP-FPM di sini
hanya mendengarkan soket Unix. Kalau ia dipindah ke TCP yang bisa dijangkau
dari luar, daftar itu harus jadi alamat proxy yang sebenarnya: header palsu
dari luar akan dipercaya bulat-bulat.

### `Unable to create a directory` / 500 setelah menulis berkas

**Dua pengguna sama-sama perlu menulis ke `storage/`,** dan itu yang membuat
perbaikan naifnya berputar:

- **PHP-FPM** berjalan sebagai `www-data` dan menulis cache, sesi, log, serta
  setiap unggahan.
- **`php artisan`** berjalan sebagai pengguna deploy Anda, dan menulis hal yang
  sama saat menjalankan perintah.

Memberikan kepemilikan ke salah satunya mematahkan yang lain. `chown www-data`
membuat `php artisan` gagal dengan `UnableToCreateDirectory`; mengembalikannya
ke pengguna deploy membuat situsnya 500 pada unggahan pertama.

Jawabannya grup bersama, sekali:

```bash
sudo usermod -aG www-data oredo
sudo chown -R oredo:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

`2775` itu **setgid**: berkas dan folder baru mewarisi grup `www-data`
sendiri, jadi susunannya tidak rusak lagi setiap kali salah satu pihak membuat
sesuatu. Tanpa bit itu, perbaikannya bertahan sampai unggahan berikutnya.

`usermod` baru berlaku setelah **login ulang** — `groups` yang memastikannya.

Kalau `MEDIA_ROOT` dan `MEDIA_PRIVATE_ROOT` dipakai (§3), keduanya butuh
perlakuan yang sama; `storage/` bukan lagi satu-satunya tempat menulis.

### CORS ditolak walau domainnya sudah didaftarkan

```
The 'Access-Control-Allow-Origin' header has a value
'https://fed-web.pborado.com/' that is not equal to the supplied origin.
```

Bacalah dua nilainya berdampingan: yang satu berakhir **garis miring**. Sebuah
origin tidak pernah punya path, dan browser membandingkannya sebagai string
mentah — jadi `https://situs.com/` dan `https://situs.com` adalah dua hal
berbeda, walau terlihat sama.

`config/cors.php` sekarang membuang garis miring dan spasi di ujung, jadi yang
terlanjur terketik tetap bekerja. Sesudah mengubah `.env`:

```bash
php artisan config:clear
```

Muncul saat NAVIGASI di dalam situs, bukan saat memuat halaman pertama:
pengambilan pertama terjadi di server Nuxt (server ke server, tanpa CORS), yang
berikutnya di browser.

### `500`, bukan 502

Kalau sudah lewat FPM tapi membalas 500, tiga sebab paling sering:

```bash
tail -50 storage/logs/laravel.log

ls vendor/ >/dev/null || composer install --no-dev --optimize-autoloader
grep -q '^APP_KEY=base64:' .env || php artisan key:generate
sudo chown -R www-data:www-data storage bootstrap/cache
```

Yang terakhir yang paling sering terlewat: PHP-FPM berjalan sebagai
`www-data`, dan Laravel menulis ke `storage/` dan `bootstrap/cache` pada tiap
permintaan. Berkas yang dimiliki pengguna deploy membuatnya 500 di permintaan
pertama — dan pesannya ada di log Laravel, bukan di log nginx.

### Layar menampilkan KUNCI, bukan kalimat

Gejalanya: daftar Legal Pages menulis `legal.names.privacy-policy`, tombol
menulis `news.field_title`. Terjadi 2026-09-04 sesudah deploy.

**Ini hampir selalu FPM yang belum di-reload, bukan terjemahan yang hilang.**
Alasannya ada di §8: `opcache.validate_timestamps=0` membuat PHP berhenti
memeriksa apakah berkasnya berubah — dan berkas bahasa (`lang/{en,id}/*.php`)
adalah berkas PHP biasa, jadi ia ikut membeku bersama sisa aplikasi. Aset Vue
TIDAK ikut membeku: ia berkas statis yang disajikan nginx langsung. Jadi
sesudah `git pull` + `bun run build` tanpa reload, yang berjalan adalah
**layar baru di atas kamus lama** — dan kunci yang baru lahir tidak ada di
kamus itu.

Bentuk kegagalannya khas dan layak dihafal: **JS-nya terbaca baru, PHP-nya
terbaca lama.** Kalau yang muncul justru tampilan versi sebelumnya, itu
sebaliknya — asetnya yang belum ter-build.

```bash
sudo systemctl reload php8.4-fpm
```

Kalau sesudah reload masih mencetak kunci, barulah terjemahannya memang belum
ada. Periksa di server:

```bash
php artisan tinker --execute="print_r(array_keys(trans('backoffice.legal.names')));"
```

Kosong atau kurang satu berarti `lang/` belum ikut ter-pull. `git log -1` di
server, cocokkan dengan commit yang Anda kira sudah tayang — bukan dengan
commit yang ada di mesin Anda.

### `404` di host API

**Untuk `/` itu BENAR** — host API hanya melayani `/api`, dan `location / {
return 404; }` yang melakukannya. Ujilah pathnya, bukan domainnya:

```bash
curl -si https://fed-api.pborado.com/api/v1/news | head -1   # 200 = sehat
curl -si https://fed-api.pborado.com/login       | head -1   # 404 = pembatasan bekerja
```

Kalau `/api/v1/news` juga 404, barulah ada yang salah — periksa `server_name`,
lalu pastikan `root` menunjuk `public/` dan bukan akar project.

### Nama domainnya sendiri tidak ketemu

Bukan nginx. `dig +short fed-api.pborado.com` — kalau kosong, DNS-nya yang
belum menunjuk ke mana pun.

---

## 10. Mesin pencari

**`robots.txt` tidak mencegah pengindeksan.** Ia mencegah PERAYAPAN. URL yang
diblokir di sana tetap bisa muncul di hasil pencarian — tanpa cuplikan, tapi
muncul — kalau ada situs lain yang menautkannya. Yang benar-benar menahan
sebuah halaman adalah `noindex`.

Keduanya juga saling melemahkan kalau dipakai sendiri-sendiri: perayap yang
dilarang MERAYAP tidak akan pernah membaca `noindex` di halamannya. Jadi
keduanya dipasang bersama.

### Backoffice, API, dan host media — tertutup SELAMANYA

Tidak ada satu pun halaman di ketiganya yang berguna di hasil pencarian, dan
halaman login yang terindeks cuma mengundang percobaan masuk.

- `public/robots.txt` menolak seluruh perayap.
- Ketiga blok nginx mengirim `X-Robots-Tag: noindex, nofollow, noarchive`
  dengan `always` — supaya ia ikut pada respons galat juga: halaman 404 dan 500
  sama tidak bergunanya di hasil pencarian, dan `add_header` tanpa `always`
  hanya berlaku untuk 2xx dan 3xx.

Ini bukan setelan sementara. Tidak ada keadaan di mana backoffice pantas
terindeks.

### Situs publik — tertutup SAMPAI diumumkan

Satu sakelar, di `ecosystem.config.cjs`:

```js
NUXT_PUBLIC_ALLOW_INDEXING: "false",   // "true" saat peluncuran
```

Selama `false`: tiap halaman membawa `<meta name="robots" content="noindex,
nofollow, noarchive">`, dan `/robots.txt` menolak semuanya. Selama itu pula
**server mengatakannya di log tiap restart** — satu baris yang jauh lebih murah
daripada percakapan yang dimulai dengan "kenapa situsnya tidak ketemu di
Google".

Bawaannya tertutup karena kedua kegagalannya tidak sama beratnya: lupa membuka
berarti kehilangan trafik, yang terlihat di Search Console dalam hitungan hari
dan pulih penuh; lupa menutup berarti isi yang belum jadi masuk indeks, dan
mengeluarkannya butuh permintaan penghapusan dan berminggu-minggu.

Saat peluncuran, itu satu-satunya yang perlu diubah:

```bash
pm2 restart dwf-nuxt --update-env
curl -s https://fed-web.pborado.com/robots.txt        # Disallow: kosong
curl -s https://fed-web.pborado.com/ | grep -c 'name="robots"'   # 0
```

---

## 11. Yang HARUS dijalankan tiap deploy

```bash
php artisan migrate --force
php artisan db:seed --class=AccessSeeder   # kalau ada modul/izin baru
                                           # (pemasangan PERTAMA: `dwf:install`, lihat §6)
php artisan storage:link                    # lihat §2
php artisan config:cache && php artisan route:cache && php artisan view:cache
bun install && bun run build

sudo systemctl reload php8.4-fpm            # WAJIB, lihat di bawah
```

**Reload FPM bukan pelengkap kalau `opcache.validate_timestamps=0`** (§8).
Dengan setelan itu PHP berhenti memeriksa apakah berkasnya berubah, jadi kode
baru tidak akan terpakai sampai worker-nya diganti. Lupa satu kali berarti
deploy yang "berhasil" tapi situsnya tetap menjalankan versi lama — dan tidak
ada satu pun tanda yang memberi tahu.

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

## 12. Daftar IP — cara mengunci diri sendiri

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

## 13. Skala: satu server vs beberapa

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

## 14. Situs publik menunggu satu nilai

`landing-page-nuxt` membaca API ini lewat `NUXT_PUBLIC_API_BASE_URL`. Nilainya
harus menunjuk `https://domain-backoffice/api/v1`, dan domain situs publiknya
harus ada di `CORS_ALLOWED_ORIGINS` di sisi sini. Keduanya harus benar; salah
satu saja membuat situs publik jatuh ke mock atau ke galat CORS.

**`registrationLabel` dari API tidak boleh di-cache lebih dari sehari.** Ia
teks siap tampil ("in 3 days"), bukan timestamp — halaman yang menyimpannya di
edge cache akan terus menulis "3 days" sampai minggu berikutnya.

---

## 15. Surel

### Aplikasi ini mengirim SATU surel, dan itu kunci pintunya

`AdminInvitationMail` satu-satunya. Tidak ada reset sandi — undangan itu sendiri
jalur pemulihannya. Pesan kontak hanya tersimpan di database dan dibaca di
backoffice; pelanggan buletin dikumpulkan tapi belum ada yang mengirimi mereka
apa pun.

Jadi volumenya belasan surel seumur project. Tapi **akun admin dibuat tanpa
sandi**, dan tautan sekali pakai 72 jam itu satu-satunya cara pemiliknya bisa
masuk pertama kali. Mendarat di spam = orangnya terkunci di luar.

Itu yang menentukan pilihan penyedianya: bukan harga, bukan fitur, melainkan
penempatan inbox dan akun yang tidak tiba-tiba ditangguhkan.

### `MAIL_MAILER=log` adalah kegagalan yang terlihat seperti keberhasilan

Bawaan `.env.example`, karena benar untuk pengembangan. Di server ia berarti:
surelnya **berhasil dikirim** ke `storage/logs/laravel.log`, layar backoffice
mengatakan undangan terkirim, dan tidak seorang pun pernah menerimanya. Tautan
penerimaan berikut tokennya duduk di berkas log itu dalam bentuk polos.

Tidak ada galat, tidak ada peringatan. Periksa dengan perintah di bawah, bukan
dengan mata.

### Pilihan penyedia

**Resend** — 3.000/bulan gratis (100/hari), SMTP biasa, punya region EU.
Cukup dengan kelebihan besar untuk kebutuhan di atas.

Kalau undangan tetap masuk spam, naik ke **Postmark**: penempatan transaksional
terbaik di kelasnya, dan ia memisahkan aliran transaksional dari broadcast
secara desain. **Brevo** atau **Mailjet** kalau residency EU jadi syarat.

**SendGrid tidak lagi punya paket gratis** (dipensiunkan 27 Mei 2025); pintu
masuknya ~$19,95/bulan, dan di tier itu Anda berbagi kolam IP dengan lalu lintas
bulk siapa saja.

**Jangan** memakai postfix/sendmail server ini sendiri: IP baru tanpa reputasi,
tanpa PTR yang selaras, dan port 25 keluar sering diblokir hoster — surelnya
hilang tanpa satu pun galat. **Jangan** memakai SMTP akun Gmail pribadi: ia
gagal alignment DMARC untuk domain Anda, dan itu kredensial bersama.

### Aturan yang berlaku sejak modul buletin hidup

Jangan pernah mengirim buletin dari reputasi yang sama dengan undangan admin.
Satu lonjakan keluhan spam dari blast buletin bisa menenggelamkan surel yang
jadi kunci masuk backoffice. Akun atau aliran terpisah, bukan kuota yang sama.

### Menyiapkan Resend

**0. Putuskan domain pengirimnya lebih dulu.** Yang dibaca penerima adalah
alamat From, dan mengganti domain pengirim setelah reputasinya terbangun berarti
mulai dari nol. Pakai domain merek yang akan tetap benar setelah peluncuran,
bukan domain infrastruktur.

**1. Tambahkan domainnya** di Resend → Domains → Add Domain, pilih region
(`eu-west-1` kalau residency EU relevan).

**2. Pasang record DNS yang ia tampilkan.** Bentuknya kira-kira begini —
**salin nilai persisnya dari layar Resend**, karena host dan region berbeda per
akun:

```
TXT   resend._domainkey        p=MIGfMA0GCSq...        ← DKIM
TXT   send                     v=spf1 include:amazonses.com ~all
MX    send                     feedback-smtp.<region>.amazonses.com   (prio 10)
```

Tambahkan DMARC sendiri, mulai dari mode yang tidak menolak apa pun:

```
TXT   _dmarc    v=DMARC1; p=none; rua=mailto:dmarc@<domain-anda>
```

Naikkan ke `p=quarantine` setelah laporannya bersih beberapa minggu. Sejak 2024
Gmail dan Yahoo menuntut SPF, DKIM, dan DMARC selaras.

**3. Terbitkan API key** (Sending access, batasi ke domain itu).

**4. Isi `.env`:**

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.resend.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=resend                       # literal, bukan email Anda
MAIL_PASSWORD=re_xxxxxxxxxxxxxxxxxxxx      # API key-nya
MAIL_FROM_ADDRESS="no-reply@<domain-anda>"
MAIL_FROM_NAME="Domino World Federation"
```

SMTP, bukan transport API `resend` bawaan Laravel — tanpa dependensi baru, dan
pindah penyedia nanti cukup mengganti empat baris di atas.

`MAIL_FROM_ADDRESS` **wajib** di domain yang barusan diverifikasi. Alamat gmail
di sana akan gagal DMARC dan ditolak diam-diam.

**5. Buang config cache, lalu reload FPM:**

```bash
php artisan config:clear     # atau config:cache lagi
sudo systemctl reload php8.4-fpm
```

Reload-nya bukan pelengkap. `bootstrap/cache/config.php` adalah berkas PHP, dan
dengan `opcache.validate_timestamps=0` (§8) ia tidak dilepas sampai worker-nya
diganti — jadi `.env` yang sudah benar tetap tidak terbaca. Gejalanya sama
persis dengan §9 "Layar menampilkan KUNCI": perubahan yang "sudah dilakukan"
tapi tidak berlaku.

**6. Uji:**

```bash
php artisan dwf:mail-test anda@example.com
```

Ia mencetak konfigurasi yang **benar-benar dibaca aplikasi** — bukan isi `.env`,
karena persis di situ keduanya bisa berbeda — lalu mengirim satu surel dan
melaporkan galat aslinya kalau gagal. `MAIL_MAILER=log` dilaporkan sebagai
KEGAGALAN, bukan sukses.

**7. Periksa folder spam juga.** "Terkirim" bukan "masuk inbox". Kalau ia
mendarat di spam, yang kurang hampir selalu DKIM atau DMARC — bukan kodenya.

### Kalau DNS domainnya tidak bisa disentuh

Kirim **sebagai** `no-reply@domain-anda` tanpa menyentuh DNS-nya tidak mungkin,
dan itu bukan kerewelan penyedia: SPF dan DKIM justru **buktinya** bahwa Anda
boleh memakai nama domain itu. Yang tanpa bukti akan dibuang penerima —
sekarang lebih sering daripada dulu.

Jadi jalan keluarnya bukan "kirim tanpa autentikasi", melainkan **kirim dari
alamat yang domainnya sudah diautentikasi orang lain**: sebuah mailbox yang
memang Anda punya.

**Gmail atau Google Workspace lewat SMTP + App Password.** Nol record DNS,
karena `gmail.com` (atau domain Workspace Anda) sudah punya SPF dan DKIM sejak
lama.

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=akun-anda@gmail.com
MAIL_PASSWORD="xxxx xxxx xxxx xxxx"      # App Password 16 karakter, BUKAN sandi akun
MAIL_FROM_ADDRESS="akun-anda@gmail.com"  # WAJIB sama dengan MAIL_USERNAME
MAIL_FROM_NAME="Domino World Federation"
```

App Password butuh 2-Step Verification menyala di akun Google itu; "Less secure
app access" sudah dimatikan Google sejak 1 Mei 2025, jadi sandi akun biasa
tidak akan bekerja. Batasnya 500/hari (Gmail biasa) atau 2.000/hari (Workspace)
— jauh di atas kebutuhan aplikasi ini.

**`MAIL_FROM_ADDRESS` harus sama dengan akun yang login.** Kalau diisi alamat
lain, Gmail menulis ulang From-nya jadi alamat akun — jadi `.env` mengatakan
satu hal dan yang dibaca penerima hal lain, tanpa galat apa pun.

Yang ditukar, dan sebaiknya disadari sejak awal:

- Undangan admin datang **dari alamat Gmail**, bukan dari domain federasi. Untuk
  surel yang isinya "klik ini untuk membuat akun admin", alamat pengirim yang
  tidak resmi persis yang diajarkan kepada orang untuk dicurigai.
- App Password adalah kredensial yang memberi hak **mengirim sebagai** pemilik
  mailbox itu. Ia duduk di `.env` server. Pakai akun khusus untuk aplikasi ini,
  jangan akun pribadi siapa pun.
- Tidak ada log pengiriman, webhook, maupun laporan bounce. Kalau surelnya tidak
  sampai, tidak ada tempat untuk melihat kenapa.

Ini setelan yang **cukup untuk sekarang** dan sepenuhnya bisa dibalik: pindah ke
Resend nanti mengganti empat baris yang sama.

Alternatif tanpa DNS yang lain — **Brevo** memperbolehkan memverifikasi satu
alamat pengirim lewat tautan di inbox, tanpa record apa pun. Ia bekerja, tapi
lebih buruk daripada opsi di atas untuk kasus ini: From-nya domain Anda
sementara yang menandatangani domain Brevo, jadi tidak ada yang selaras dan
peluang mendarat di spam naik — persis pada satu surel yang paling tidak boleh
mendarat di sana.

### Antrean: jebakan yang belum meledak

`QUEUE_CONNECTION=database`, tapi **tidak ada worker yang jalan** — cron di
server cuma `schedule:run` (§1). Hari ini aman: tidak satu pun job di-dispatch,
dan undangan dikirim sinkron.

Tapi begitu ada yang menambahkan `implements ShouldQueue` ke sebuah Mailable,
surelnya berhenti terkirim **tanpa satu pun galat** — ia masuk tabel `jobs` dan
duduk di sana selamanya. Kalau antrean benar-benar dibutuhkan, worker-nya
dipasang bersamaan, bukan menyusul.
