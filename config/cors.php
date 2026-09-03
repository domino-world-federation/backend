<?php

return [
    /*
     * CORS untuk API publik.
     *
     * Hanya `api/*` — halaman backoffice dilayani dari domainnya sendiri dan
     * tidak pernah dipanggil lintas asal. Membuka `*` di sini berarti setiap
     * situs mana pun bisa memanggil endpoint tulis atas nama pengunjung yang
     * sedang login.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],

    /*
     * Daftar asal yang diizinkan, dari `.env`.
     *
     * Sengaja BUKAN `['*']`. Situs publik hidup di satu domain yang diketahui,
     * dan wildcard di sini tidak menambah satu pun kemampuan yang dibutuhkannya
     * — ia cuma membuat endpoint tulis bisa dipanggil dari mana saja.
     *
     * Kosong = tidak ada yang diizinkan lintas asal, dan itu default yang
     * benar: lupa mengisinya menghasilkan galat CORS yang terlihat di konsol,
     * bukan lubang yang tidak terlihat di mana pun.
     */
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // API-nya read-only tanpa autentikasi, jadi tidak ada cookie yang perlu
    // ikut. Menyalakannya melarang `allowed_origins: *` dan menambah permukaan
    // serangan untuk nol manfaat.
    'supports_credentials' => false,
];
