/**
 * Dua proses latar Laravel di bawah PM2: penjadwal dan pekerja antrean.
 *
 * PM2 sudah menyalakan situs publik di server ini, jadi penjadwal ikut ke sana:
 * satu tempat untuk melihat apa yang hidup (`pm2 ls`) dan satu tempat untuk
 * melihat kenapa ia mati (`pm2 logs dwf-scheduler`). Crontab tidak punya
 * keduanya — pekerjaan yang gagal diam, dan satu-satunya jejaknya surel ke
 * mailbox lokal yang tidak pernah dibaca siapa pun.
 *
 * ── Namanya `.cjs`, BUKAN `.js` ──
 *
 * `package.json` punya `"type": "module"`, jadi berkas `.js` diparse sebagai
 * ESM: `module.exports` tidak melakukan apa-apa, `apps` jadi `undefined`, dan
 * PM2 tidak mengeluh — ia cuma tidak menjalankan apa pun. Jebakan yang sama
 * persis dengan `../landing-page-nuxt/ecosystem.config.cjs`.
 *
 * ── `schedule:work`, BUKAN `schedule:run` ──
 *
 * Yang kedua sekali jalan lalu keluar — itu yang dipanggil cron tiap menit. Di
 * bawah PM2 ia berarti proses yang selesai dalam sedetik, dinyalakan lagi, dan
 * seterusnya: penjadwal yang berputar liar alih-alih berjalan. Yang pertama
 * tinggal hidup dan memanggil `schedule:run` sendiri tiap menit sebagai
 * subproses.
 *
 * ── Pasang ──
 *
 *   pm2 start ecosystem.config.cjs
 *   pm2 save                 # supaya ia kembali setelah reboot
 *   pm2 startup              # sekali per server, ikuti perintah yang dicetaknya
 *
 * `pm2 save` bukan pelengkap: tanpa itu, reboot berikutnya mematikan penjadwal
 * dan tidak ada satu pun layar yang memberi tahu. Gejalanya sama persis dengan
 * cron yang lupa dipasang — disk penuh berbulan-bulan kemudian.
 *
 * ── Pekerja antrean: `dwf-queue` ──
 *
 * Ditambahkan 2026-09-05 bersama pemberitahuan formulir situs publik. Seluruh
 * notifikasi `ShouldQueue`, jadi TANPA proses ini tidak ada satu pun surel yang
 * keluar dan lonceng di backoffice tidak pernah terisi — sementara situs publik
 * tetap membalas 204 dan tidak ada satu pun layar yang memperlihatkan bahwa
 * antreannya menumpuk. Gejalanya identik dengan "tidak ada yang mengirim
 * formulir", yang adalah kesimpulan yang salah.
 *
 * Periksa dengan `php artisan queue:monitor database:default` atau, paling
 * cepat, `select count(*) from jobs` — angka yang naik dan tidak pernah turun
 * berarti prosesnya mati.
 *
 * `--tries=3` dan `--backoff=60`: SMTP gagal karena hal-hal yang sembuh sendiri
 * (rate limit penyedia, jaringan), dan pemberitahuan yang menyerah pada
 * percobaan pertama adalah laporan integritas yang tidak pernah dikabarkan.
 * Yang gagal tiga kali masuk `failed_jobs` dan bisa dikirim ulang dengan
 * `php artisan queue:retry all`.
 *
 * `--max-time=3600`: pekerja Laravel memuat aplikasi SEKALI dan hidup terus,
 * jadi kode baru tidak ikut sampai ia dinyalakan ulang. Membiarkannya mati
 * sendiri tiap jam berarti deploy yang lupa `pm2 restart dwf-queue` tetap
 * sampai — dalam sejam, bukan tidak pernah.
 *
 * ── Jangan dijalankan bersama crontab ──
 *
 * Kalau `* * * * * php artisan schedule:run` masih ada di crontab, matikan.
 * Dua penjadwal berarti tiap pekerjaan berpeluang jalan dua kali; untuk
 * `editor:prune` itu tidak berbahaya, tapi ia menghabiskan I/O dua kali dan
 * membuat log terbaca seolah ada yang salah.
 */
module.exports = {
  apps: [
    {
      name: 'dwf-scheduler',

      /*
       * Berkas ini ter-commit, jadi ia tidak boleh memuat path satu mesin.
       * `__dirname` adalah folder berkas ini sendiri — yaitu akar project —
       * sehingga config yang sama bekerja di server mana pun tanpa disunting.
       * Path absolut yang di-hardcode akan "bekerja" di mesin yang menulisnya
       * dan gagal di mesin berikutnya, dengan galat yang menunjuk ke folder
       * milik orang lain.
       */
      cwd: __dirname,

      script: 'php',
      args: 'artisan schedule:work',

      // Satu proses, tidak pernah lebih. Penjadwal yang berjalan dua kali
      // adalah pekerjaan yang berjalan dua kali.
      instances: 1,
      exec_mode: 'fork',

      autorestart: true,

      // `schedule:work` memang hidup selamanya; kalau ia mati dalam hitungan
      // detik berulang kali, itu galat yang perlu dilihat orang — bukan sesuatu
      // yang pantas dicoba lagi selamanya. PM2 menyerah setelah 10 kali dan
      // menandainya `errored` di `pm2 ls`.
      max_restarts: 10,
      min_uptime: '60s',

      // Prosesnya tipis (ia cuma memanggil subproses tiap menit), jadi angka
      // ini murni jaring pengaman terhadap kebocoran di masa depan.
      max_memory_restart: '256M',

      // Log dibiarkan di tempat bawaan PM2 (`~/.pm2/logs/dwf-scheduler-*.log`),
      // yang mengikuti pengguna yang menjalankannya. Menuliskannya di sini
      // berarti mengunci berkas ini ke satu home directory lagi.
      time: true,
    },

    {
      name: 'dwf-queue',

      cwd: __dirname,

      script: 'php',
      args: 'artisan queue:work --tries=3 --backoff=60 --max-time=3600',

      /*
       * Satu pekerja. Notifikasi di aplikasi ini datang beberapa per jam pada
       * hari tersibuknya — pekerja kedua hanya menambah proses yang menunggu.
       * Kalau suatu saat memang perlu, naikkan angkanya; jangan menyalakan
       * `queue:work` kedua dengan tangan, karena yang itu tidak ikut mati saat
       * PM2 dimatikan.
       */
      instances: 1,
      exec_mode: 'fork',

      autorestart: true,

      // `--max-time=3600` membuatnya keluar dengan sukses tiap jam, dan PM2
      // menyalakannya lagi. Itu WAJAR di sini — jadi `min_uptime` diberi jarak
      // jauh dari satu jam supaya restart terjadwal itu tidak dihitung sebagai
      // kegagalan.
      max_restarts: 10,
      min_uptime: '60s',

      max_memory_restart: '256M',

      time: true,
    },
  ],
}
