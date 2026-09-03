import { onMounted, onUnmounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import { sinceNavigationStart } from '@/navigationClock'

/**
 * Filter, pencarian, dan pindah halaman untuk layar daftar.
 *
 * Semua daftar CMS memakai pola yang sama: beberapa dropdown, satu kotak cari,
 * hasilnya ada di URL. Ada di URL supaya halaman hasil filter bisa ditandai,
 * dibagikan, dan bertahan saat tombol back ditekan.
 *
 * Pencarian ditunda 300 ms. Tanpa itu tiap ketukan huruf jadi satu request, dan
 * jawaban yang datang tidak berurutan bisa menimpa hasil yang lebih baru.
 *
 * `loading` yang dikembalikan dipakai halaman untuk menampilkan `SkeletonTable`.
 * Ia menyala untuk filter, pencarian, pindah halaman, DAN untuk kedatangan
 * halaman ini sendiri — termasuk lewat sidebar, tombol Back, atau URL yang
 * diketik langsung.
 */

/**
 * Skeleton bertahan minimal selama ini, dihitung sejak permintaan DIMULAI.
 *
 * Tanpa penahan ini skeleton-nya secara teknis sudah benar dan tetap tidak
 * pernah terlihat: `onStart` dan `onFinish` di server lokal hanya berjarak
 * 14–21 ms, jadi ia menyala dan padam di dalam satu frame — persis penyakit
 * yang dulu membuat bar progres Inertia dikira salah warna, padahal ia memang
 * tidak pernah sempat digambar.
 *
 * 420 ms dipilih supaya rangkanya terbaca sebagai "sedang mengambil data", bukan
 * sebagai kedipan. Ia TIDAK memperlambat datanya — barisnya sudah ada di memori
 * sejak jauh sebelum itu; yang ditahan cuma pergantian gambarnya.
 */
const MIN_SKELETON_MS = 420
export function useIndexFilters(url: string, initial: Record<string, string>) {
    const state = ref<Record<string, string>>({ ...initial })

    /*
     * Menyala SEJAK AWAL, bukan mulai dari `false`.
     *
     * Komponen halaman Inertia dipasang setelah propsnya tiba, jadi kalau
     * penanda ini dimulai dari `false`, tabelnya sudah tergambar penuh sebelum
     * ada kesempatan menampilkan apa pun — dan pindah halaman lewat sidebar
     * terasa seperti tidak ada yang terjadi sampai isinya tiba-tiba berganti.
     *
     * Yang memadamkannya `onMounted` di bawah, dihitung dari saat kunjungannya
     * benar-benar dimulai — bukan dari saat komponen ini dipasang.
     */
    const loading = ref(true)
    let timer: ReturnType<typeof setTimeout> | undefined

    /** Penahan sisa waktu minimal skeleton. */
    let hold: ReturnType<typeof setTimeout> | undefined
    let shownAt = 0

    function startLoading(): void {
        clearTimeout(hold)
        shownAt = performance.now()
        loading.value = true
    }

    function stopLoading(): void {
        const remaining = MIN_SKELETON_MS - (performance.now() - shownAt)

        if (remaining <= 0) {
            loading.value = false
            return
        }

        hold = setTimeout(() => (loading.value = false), remaining)
    }

    function visit(extra: Record<string, string | number> = {}): void {
        // Kunci kosong dibuang supaya URL-nya tetap pendek dan bisa dibaca.
        const query = {
            ...Object.fromEntries(Object.entries(state.value).filter(([, v]) => v !== '')),
            ...extra,
        }

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: startLoading,
            onFinish: stopLoading,
        })
    }

    function set(key: string, value: string): void {
        state.value = { ...state.value, [key]: value }
        visit()
    }

    /** Pindah halaman — ikut menyalakan skeleton, sama seperti filter. */
    function go(page: number): void {
        visit({ page })

        /*
         * Naik ke puncak daftar, dan naiknya DIGULIR.
         *
         * Kunjungannya `preserveScroll: true` — benar untuk filter (orangnya
         * sedang berdiri di kotak filter, di atas), tapi salah untuk pindah
         * halaman: tombol Next ada di KAKI tabel, jadi tanpa ini halaman 2
         * dibuka dengan mata sudah berada di baris terakhirnya dan baris
         * pertama tidak pernah terlihat.
         *
         * `behavior` sengaja tidak diisi: tanpa argumen itu, browser memakai
         * `scroll-behavior` dari CSS — dan di sana sudah ada aturan
         * `prefers-reduced-motion` yang mematikannya. Menulis
         * `behavior: 'smooth'` di sini justru MENIMPA setelan orang itu.
         */
        window.scrollTo({ top: 0 })
    }

    watch(
        () => state.value.q,
        () => {
            clearTimeout(timer)
            timer = setTimeout(() => visit(), 300)
        },
    )

    /*
     * Kedatangan halaman ini dihitung sebagai satu masa tunggu penuh.
     *
     * Sisanya diukur dari `sinceNavigationStart()`, bukan dari sekarang: kalau
     * server memang butuh 300 ms, rangkanya cukup tampil 120 ms lagi. Yang
     * ditahan selalu sama panjang di mata orang, entah datanya datang cepat
     * atau lambat — dan itu justru yang membuatnya berhenti terasa seperti
     * kedipan acak.
     */
    onMounted(() => {
        const remaining = MIN_SKELETON_MS - sinceNavigationStart()

        if (remaining <= 0) {
            loading.value = false
            return
        }

        hold = setTimeout(() => (loading.value = false), remaining)
    })

    // Kedua timer digantung di luar siklus hidup komponen. Tanpa dibersihkan,
    // yang menyala 400 ms setelah halaman ditinggalkan adalah `loading` milik
    // komponen yang sudah tidak ada.
    onUnmounted(() => {
        clearTimeout(timer)
        clearTimeout(hold)
    })

    return { state, set, go, loading, visit }
}
