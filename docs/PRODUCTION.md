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

  ```nginx
  location ^~ /storage/ {
      location ~ \.php$ { return 403; }
      # opsional tapi disarankan: paksa unduh, jangan render
      add_header X-Content-Type-Options nosniff;
  }
  ```

  Validasi sudah menolak apa pun selain WebP dan PDF (`mimes:` membaca mime
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

## 6. Yang HARUS dijalankan tiap deploy

```bash
php artisan migrate --force
php artisan db:seed --class=AccessSeeder   # kalau ada modul/izin baru
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

## 7. Daftar IP — cara mengunci diri sendiri

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

## 8. Skala: satu server vs beberapa

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

## 9. Situs publik menunggu satu nilai

`landing-page-nuxt` membaca API ini lewat `NUXT_PUBLIC_API_BASE_URL`. Nilainya
harus menunjuk `https://domain-backoffice/api/v1`, dan domain situs publiknya
harus ada di `CORS_ALLOWED_ORIGINS` di sisi sini. Keduanya harus benar; salah
satu saja membuat situs publik jatuh ke mock atau ke galat CORS.

**`registrationLabel` dari API tidak boleh di-cache lebih dari sehari.** Ia
teks siap tampil ("in 3 days"), bukan timestamp — halaman yang menyimpannya di
edge cache akan terus menulis "3 days" sampai minggu berikutnya.
