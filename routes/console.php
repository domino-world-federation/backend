<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Jadwal
|--------------------------------------------------------------------------
|
| Keduanya membereskan hal yang TUMBUH TANPA BATAS kalau dibiarkan, dan
| keduanya tidak akan pernah memberi tahu siapa pun kalau berhenti jalan —
| jadi `onOneServer()` dan `withoutOverlapping()` dipasang sejak sekarang,
| bukan nanti saat ada server kedua.
|
| WAJIB ADA DI SERVER: satu entri cron yang memanggil scheduler-nya,
|
|     * * * * * cd /path/ke/app && php artisan schedule:run >> /dev/null 2>&1
|
| Tanpa baris itu keduanya tidak pernah jalan, dan tidak ada satu pun layar
| yang memperlihatkannya.
|
*/

/*
 * Gambar editor yang tidak disebut HTML mana pun.
 *
 * Mingguan, bukan harian: yang dibersihkannya menumpuk pelan, dan tiap
 * eksekusi membaca kolom HTML seluruh modul. Ambang 7 hari yang membuatnya
 * aman — gambar diunggah saat disisipkan, bukan saat formulirnya disimpan,
 * jadi ada jendela nyata di mana berkas sudah ada tapi belum dirujuk.
 */
Schedule::command('editor:prune --days=7')
    ->weeklyOn(1, '03:10')
    ->onOneServer()
    ->withoutOverlapping();

/*
 * Log aktivitas.
 *
 * `activitylog.delete_records_older_than_days` (config paketnya) yang
 * menentukan batasnya. Ia jejak audit, jadi retensinya keputusan kebijakan,
 * bukan kenyamanan — angkanya di config supaya bisa diubah tanpa menyentuh
 * jadwal ini.
 */
Schedule::command('activitylog:clean')
    ->dailyAt('03:30')
    ->onOneServer()
    ->withoutOverlapping();
