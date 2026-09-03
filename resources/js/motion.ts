import type { Options } from 'motion-v'

/**
 * Tetapan gerak — satu sumber, persis seperti token warna di `app.css`.
 *
 * Alasannya sama: durasi yang ditulis ulang tiap komponen akan menyimpang
 * (0.2 di satu tempat, 0.25 di tempat lain), dan yang terasa bukan "salah satu
 * lebih lambat" melainkan "aplikasinya tidak rapi". Semua gerak di backoffice
 * mengambil dari sini.
 *
 * Yang TIDAK ada di sini: pemeriksaan `prefers-reduced-motion`. Itu ditangani
 * satu kali di akar lewat `<MotionConfig reduced-motion="user">` di `app.ts` —
 * Motion lalu membuang animasi transform/layout di seluruh pohon dan menyisakan
 * opacity. Menaruhnya di tiap komponen berarti suatu saat ada yang lupa.
 */
type Transition = NonNullable<Options['transition']>

/** Ease-out tajam (expo). Untuk sesuatu yang MASUK dan berhenti. */
export const EASE_OUT: [number, number, number, number] = [0.16, 1, 0.3, 1]

/** Ease standar Material. Untuk pergantian warna dan pudar dua arah. */
export const EASE_STANDARD: [number, number, number, number] = [0.4, 0, 0.2, 1]

/** Pudar murni. Overlay, crossfade tabel, teks yang berganti. */
export const FADE: Transition = { duration: 0.18, ease: EASE_STANDARD }

/** Pudar + geser pendek. Baris tabel, kartu, isi yang baru datang. */
export const RISE: Transition = { duration: 0.26, ease: EASE_OUT }

/**
 * Pegas rapat — nyaris tidak memantul.
 *
 * Dipakai untuk yang ditekan orang (tombol, menu, modal). Pegas terasa lebih
 * hidup daripada tween di sini karena kecepatan awalnya mengikuti jarak, jadi
 * gerak pendek selesai cepat tanpa perlu durasi terpisah.
 */
export const SPRING_SNAP: Transition = {
    type: 'spring',
    stiffness: 420,
    damping: 34,
    mass: 0.7,
}

/** Pegas lebih longgar. Untuk yang BERPINDAH, bukan yang muncul (pil pagination). */
export const SPRING_SOFT: Transition = {
    type: 'spring',
    stiffness: 320,
    damping: 34,
    mass: 0.8,
}

/**
 * Jeda berurutan untuk baris tabel.
 *
 * Dibatasi 200 ms TOTAL, bukan per baris: dengan 25 ms × 10 baris memang pas,
 * tapi daftar 100 baris (Contact Messages tanpa filter) akan menyapu selama
 * dua setengah detik dan baris terakhirnya terasa macet, bukan halus.
 */
export function rowDelay(index: number): number {
    return Math.min(index * 0.025, 0.2)
}
