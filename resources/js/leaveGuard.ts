/**
 * Penahan tombol Back/Forward browser untuk halaman yang punya perubahan belum
 * disimpan.
 *
 * Ini TERPISAH dari `UnsavedGuard.vue` karena satu alasan waktu: Inertia
 * memasang pendengar `popstate`-nya sendiri di dalam `router.init()`, dan
 * pendengar itu langsung menukar komponen halaman — formulirnya hilang sebelum
 * ada yang sempat bertanya. Satu-satunya cara menghentikannya adalah
 * `stopImmediatePropagation()`, dan itu hanya bekerja kalau pendengar KITA
 * terdaftar lebih dulu.
 *
 * Karena itu `installLeaveGuard()` dipanggil di `app.ts` SEBELUM
 * `createInertiaApp()`, bukan di dalam komponen mana pun. Memindahkannya ke
 * `onMounted` akan membuatnya terdaftar belakangan dan diam-diam tidak berguna:
 * tidak ada galat, penjagaannya cuma tidak pernah menahan apa pun.
 */

interface Guard {
    /** Apakah ada yang akan hilang kalau halaman ini ditinggalkan sekarang. */
    dirty: () => boolean
    /** Menanyakannya ke orangnya. `proceed` dipanggil kalau ia memilih pergi. */
    ask: (proceed: () => void) => void
}

let guard: Guard | null = null

/**
 * Entri riwayat halaman yang sedang dijaga, direkam saat penjagaan dipasang.
 *
 * Dibutuhkan karena saat `popstate` sampai, `history.state` dan `location`
 * SUDAH berpindah ke entri tujuan — jadi tidak ada lagi yang bisa dibaca untuk
 * mengembalikan keadaan semula.
 */
let anchor: { state: unknown; url: string } | null = null

export function registerLeaveGuard(next: Guard): void {
    guard = next
    anchor = { state: window.history.state, url: window.location.href }
}

export function unregisterLeaveGuard(current: Guard): void {
    // Diperiksa dulu: dua halaman formulir yang berpindah cepat bisa membuat
    // `onMounted` yang baru berjalan sebelum `onUnmounted` yang lama, dan
    // penghapusan buta akan mencabut penjagaan halaman yang baru saja dipasang.
    if (guard === current) {
        guard = null
        anchor = null
    }
}

/**
 * Apakah dua URL menunjuk halaman yang SAMA, dengan fragmen yang berbeda.
 *
 * Fragmen tidak melepas apa pun: halamannya tetap terpasang, isian formulirnya
 * tetap di tempatnya. Dulu ini tidak diperiksa, dan akibatnya menekan satu
 * langkah di Sidebar Progress Step (`585:11561`) memunculkan dialog "perubahan
 * belum disimpan" — peringatan tentang kepergian yang tidak pernah terjadi.
 *
 * `ProgressSteps` sekarang menggulir tanpa menyentuh riwayat sama sekali, jadi
 * jalur itu sudah tidak lewat sini. Pemeriksaan ini tetap ada karena tautan
 * fragmen berikutnya — di formulir mana pun — tidak seharusnya menabrak
 * masalah yang sama lagi.
 */
function sameDocument(a: string, b: string): boolean {
    try {
        const left = new URL(a, window.location.href)
        const right = new URL(b, window.location.href)

        return left.origin === right.origin
            && left.pathname === right.pathname
            && left.search === right.search
    } catch {
        return false
    }
}

function onPopstate(event: PopStateEvent): void {
    const current = guard

    if (!current || !current.dirty() || anchor === null) {
        return
    }

    if (sameDocument(anchor.url, window.location.href)) {
        return
    }

    // Inertia tidak boleh melihat event ini sama sekali.
    event.stopImmediatePropagation()

    /*
     * Kembali berdiri di entri semula.
     *
     * `pushState` membuang seluruh entri setelah posisi sekarang lalu menaruh
     * satu entri baru — jadi tumpukannya kembali persis seperti sebelum tombol
     * Back ditekan, dan halaman formulirnya tidak pernah dilepas.
     */
    window.history.pushState(anchor.state, '', anchor.url)

    current.ask(() => {
        guard = null
        anchor = null

        /*
         * Ulangi langkah mundurnya, kali ini tanpa penjagaan.
         *
         * CATATAN: kalau yang ditekan tadi Forward, langkah ini membawa orangnya
         * mundur, bukan maju. Arah traversal tidak dilaporkan oleh `popstate`
         * dan tidak bisa disimpulkan dari `state`-nya. Back adalah yang terjadi
         * hampir selalu di halaman formulir — Forward hanya mungkin kalau ia
         * sudah mundur lebih dulu — jadi ini ditukar dengan sengaja, bukan
         * terlewat.
         */
        window.history.back()
    })
}

export function installLeaveGuard(): void {
    window.addEventListener('popstate', onPopstate)
}
