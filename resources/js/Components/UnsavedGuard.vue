<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, toRef } from 'vue'
import { router } from '@inertiajs/vue3'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { useI18n } from '@/composables/useI18n'
import { registerLeaveGuard, unregisterLeaveGuard } from '@/leaveGuard'

/**
 * Menahan kepergian dari halaman formulir selama masih ada perubahan yang belum
 * disimpan.
 *
 * Dipasang sekali per halaman formulir, tanpa membungkus apa pun:
 *
 * ```vue
 * <UnsavedGuard :dirty="form.isDirty" />
 * ```
 *
 * Empat jalan keluar dijaga, dan sengaja TIDAK dengan cara yang sama:
 *
 * | Jalan keluar                     | Yang menahan                    |
 * |----------------------------------|---------------------------------|
 * | Tutup tab, tutup browser, muat ulang, ketik URL lain | `beforeunload` — dialog bawaan browser |
 * | Tombol Cancel, sidebar, breadcrumb, `<Link>` mana pun | event `before` Inertia — `ConfirmDialog` kita |
 * | Back / Forward browser           | `popstate` di `leaveGuard.ts` — `ConfirmDialog` kita |
 *
 * Yang pertama HARUS memakai dialog bawaan: begitu tab ditutup, tidak ada lagi
 * kesempatan untuk merender apa pun. Browser sengaja tidak mengizinkan
 * kalimatnya diganti — itu perlindungan terhadap halaman yang menyandera
 * pengunjungnya — jadi bunyinya memang "Leave site? Changes you made may not be
 * saved.", bukan teks kita.
 *
 * Tombol Cancel TIDAK perlu penanganan khusus. Ia sudah berupa `<Link>` Inertia
 * (`<AppButton href="…">`), jadi ia lewat pintu yang sama dengan navigasi lain.
 */
const props = defineProps<{
    /** Umumnya `form.isDirty` dari `useForm` Inertia. */
    dirty: boolean
}>()

const { t } = useI18n()

const dirty = toRef(props, 'dirty')

/**
 * Aksi yang ditahan, disimpan sebagai thunk.
 *
 * Bentuk thunk dipilih supaya kunjungan Inertia dan langkah mundur riwayat —
 * dua hal yang tidak mirip sama sekali — bisa menunggu di dialog yang sama.
 */
const pending = ref<(() => void) | null>(null)

/**
 * Melewatkan SATU kunjungan berikutnya dari pemeriksaan.
 *
 * Dipakai saat kita sendiri yang mengulang kunjungan yang tadi ditahan; tanpa
 * ini, kunjungan ulangnya tertahan lagi oleh dialog yang baru saja dijawab.
 */
let bypassOnce = false

function ask(proceed: () => void): void {
    pending.value = proceed
}

function leave(): void {
    const proceed = pending.value
    pending.value = null
    proceed?.()
}

function stay(): void {
    pending.value = null
}

/**
 * Muat ulang penuh yang DIMINTA VITE, bukan oleh orangnya. Dev saja.
 *
 * `refresh: true` di `vite.config.js` memantau `lang/**`, `routes/**`, dan
 * `resources/views/**` (bawaan `laravel-vite-plugin`). Tiap kali salah satunya
 * disimpan, Vite memanggil `location.reload()` — dan kalau kebetulan ada
 * formulir terbuka yang sudah diketik, `beforeunload` menyala dan browser
 * menampilkan "Reload site? Changes you made may not be saved."
 *
 * Dialog itu benar secara teknis dan tidak berguna secara praktis: yang memicu
 * bukan tindakan orangnya, dan menekan Cancel hanya menyisakan halaman dengan
 * aset basi. Jadi penjaganya dilucuti untuk satu muat ulang itu saja.
 *
 * `import.meta.hot` tidak ada di build produksi, jadi seluruh blok ini hilang
 * saat di-bundle — penjagaan yang sebenarnya tidak tersentuh.
 */
let viteIsReloading = false

if (import.meta.hot) {
    import.meta.hot.on('vite:beforeFullReload', () => {
        viteIsReloading = true
    })
}

function onBeforeUnload(event: BeforeUnloadEvent): void {
    if (viteIsReloading) return
    if (!dirty.value) return

    /*
     * Keduanya ditulis dengan sengaja. `preventDefault()` adalah cara yang
     * berlaku sekarang; `returnValue` yang sudah usang masih satu-satunya yang
     * dimengerti sebagian browser, dan menghapusnya berarti di browser itu
     * dialognya diam-diam tidak muncul.
     */
    event.preventDefault()
    event.returnValue = ''
}

const guard = {
    dirty: () => dirty.value,
    ask,
}

let stopBefore: VoidFunction | undefined

onMounted(() => {
    window.addEventListener('beforeunload', onBeforeUnload)
    registerLeaveGuard(guard)

    stopBefore = router.on('before', (event) => {
        const visit = event.detail.visit

        if (bypassOnce) {
            bypassOnce = false
            return
        }

        if (!dirty.value) return

        /*
         * Hanya GET yang ditahan.
         *
         * Pengiriman formulir di aplikasi ini selalu POST/PUT/PATCH/DELETE —
         * termasuk yang membawa berkas, yang dikirim POST dengan `_method`.
         * Tanpa saringan ini, tombol Save-lah yang pertama kena tahan: ia
         * memang selalu menekan tombol saat formulirnya "kotor".
         */
        if (visit.method !== 'get') return

        const href = visit.url.href

        ask(() => {
            bypassOnce = true
            router.visit(href)
        })

        return false
    })
})

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', onBeforeUnload)
    unregisterLeaveGuard(guard)
    stopBefore?.()
})
</script>

<template>
    <ConfirmDialog
        :open="pending !== null"
        variant="info"
        :title="t('leave.title')"
        :description="t('leave.body')"
        :confirm-label="t('leave.confirm')"
        :cancel-label="t('leave.stay')"
        @confirm="leave"
        @cancel="stay"
    />
</template>
