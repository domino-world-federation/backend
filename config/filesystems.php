<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        /*
         * Disk bawaan Laravel, dipakai kerangka kerjanya sendiri.
         *
         * Sampai 2026-09-06 disk ini menampung berkas dokumen di luar
         * `MEDIA_ROOT`, dan `MediaController` satu-satunya pintunya — dokumen
         * dianggap bisa ditarik kembali, jadi tiap permintaan memeriksa sakelar
         * Visibility. Pemilik repo memutuskan seluruh dokumen federasi memang
         * untuk dibagikan; berkasnya pindah ke media publik dan
         * `MEDIA_PRIVATE_ROOT` ikut pergi. Lihat migrasi `publish_document_files`.
         *
         * Yang tersisa di sini bawaan Laravel apa adanya. Tidak ada satu pun
         * kode aplikasi ini yang menulis ke sana.
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Media yang memang untuk dilihat.
         *
         * Letak dan URL-nya BISA DIPINDAH lewat `.env`, dan keduanya menjawab
         * hal yang berbeda:
         *
         *   `MEDIA_ROOT` — di mana bytenya duduk. Menaruhnya di luar direktori
         *   aplikasi (mis. `/var/www/dwf-media`) membuat deploy bergaya rilis-
         *   simbolik tidak pernah menyentuhnya, dan backup media lepas dari
         *   backup kode.
         *
         *   `MEDIA_URL` — dari mana browser mengambilnya. Mengarahkannya ke
         *   HOSTNAME LAIN (mis. `https://media.dwf-domino.org`) menaruh berkas
         *   unggahan di origin yang berbeda dari aplikasinya: berkas jahat yang
         *   lolos ke sana berjalan di origin tanpa sesi dan tanpa hak apa pun.
         *   Itu yang dilakukan `raw.githubusercontent.com`.
         *
         *   PATH yang berbeda di host yang SAMA (`/media` menggantikan
         *   `/storage`) tidak menambah keamanan satu pun — origin-nya tetap
         *   sama. Yang menolong cuma hostname berbeda.
         *
         * Dokumen IKUT ke sini sejak 2026-09-06. Sebelumnya tidak: ia tunduk
         * pada sakelar Visibility dan keluar lewat `MediaController`. Yang
         * berubah bukan mekanismenya melainkan kenyataannya — seluruh dokumen
         * federasi memang untuk dibagikan, jadi penjagaan itu tidak membeli apa
         * pun dan hanya menambah cara untuk salah: disk kedua, izin direktori
         * tersendiri, dan satu host lagi yang harus disetel benar.
         *
         * Harganya tercatat dan diterima: sakelar Visibility menyembunyikan
         * BARIS dokumen dari situs, bukan berkasnya. Yang sudah memegang
         * tautannya tetap bisa mengunduh, dan layar Documents mengatakannya.
         */
        'public' => [
            'driver' => 'local',
            'root' => env('MEDIA_ROOT', storage_path('app/public')),
            'url' => rtrim(env('MEDIA_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/storage'), '/'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    /*
     * Symlink yang dibuat `php artisan storage:link`.
     *
     * Mengikuti `MEDIA_ROOT`, jadi ia tetap menunjuk tempat yang benar kalau
     * medianya dipindah. Kalau media disajikan dari HOST TERPISAH, symlink ini
     * jadi tidak terpakai — biarkan saja, ia tidak mengganggu; yang menyajikan
     * berkasnya adalah host itu, dan `MEDIA_URL` yang memberi tahu aplikasi ke
     * mana harus menunjuk.
     */
    'links' => [
        public_path('storage') => env('MEDIA_ROOT', storage_path('app/public')),
    ],

];
