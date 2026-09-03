import { router } from '@inertiajs/vue3'

/**
 * Kapan kunjungan yang membawa kita ke halaman sekarang DIMULAI.
 *
 * Dibutuhkan karena komponen halaman Inertia baru dipasang setelah propsnya
 * tiba — jadi dari dalam halaman itu sendiri tidak ada satu pun cara untuk tahu
 * bahwa barusan ada yang menunggu, apalagi berapa lama. Yang mencatatnya harus
 * sesuatu yang hidup melintasi pergantian halaman, dan itu berarti modul.
 *
 * Nilai awalnya diambil saat modul dievaluasi, yaitu pada muat pertama halaman.
 * Dengan begitu kunjungan langsung (ketik URL, muat ulang, buka dari bookmark)
 * ikut punya titik mulai yang masuk akal, bukan nol.
 */
let startedAt = performance.now()

export function installNavigationClock(): void {
    router.on('start', () => {
        startedAt = performance.now()
    })
}

/** Milidetik sejak kunjungan terakhir dimulai. */
export function sinceNavigationStart(): number {
    return performance.now() - startedAt
}
