import '../css/app.css'

import { createApp, h, type DefineComponent } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { MotionConfig } from 'motion-v'

import { installLeaveGuard } from './leaveGuard'
import { installNavigationClock } from './navigationClock'

const appName = import.meta.env.VITE_APP_NAME ?? 'DWF Backoffice'

/*
 * WAJIB sebelum `createInertiaApp()`, bukan sesudah dan bukan di dalam komponen.
 *
 * Inertia memasang pendengar `popstate`-nya di dalam `router.init()`, yang
 * dipanggil dari `createInertiaApp()`. Penjaga formulir menghentikan Back
 * dengan `stopImmediatePropagation()`, dan itu hanya menghentikan pendengar
 * yang terdaftar SESUDAHNYA. Dipindah satu baris ke bawah, penjagaannya masih
 * ter-compile, masih lolos tes, dan tidak pernah menahan apa pun.
 */
installLeaveGuard()
installNavigationClock()

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        createApp({
            /*
             * `MotionConfig` membungkus SELURUH aplikasi supaya
             * `reduced-motion: 'user'` berlaku sekali untuk semuanya.
             *
             * Dengan setelan itu, Motion membuang animasi transform, scale, dan
             * layout begitu sistem operasi menyalakan "Reduce Motion", dan
             * menyisakan opacity — jadi antarmukanya tetap memberi tahu bahwa
             * sesuatu berubah tanpa menggerakkannya. Menaruh pemeriksaan itu di
             * tiap komponen berarti suatu saat ada komponen baru yang lupa, dan
             * yang kena justru orang yang paling butuh setelan itu dihormati.
             */
            render: () => h(MotionConfig, { reducedMotion: 'user' }, () => h(App, props)),
        })
            .use(plugin)
            .mount(el)
    },

    progress: {
        // Emas DWF (Primary/60) — sama dengan warna toggle aktif dan pagination
        // aktif di wireframe, jadi bar progres terbaca sebagai bagian sistem
        // yang sama, bukan warna Inertia bawaan.
        color: '#E1B762',

        // Bawaannya 250 ms, dan itu berarti bar-nya TIDAK PERNAH terlihat di
        // sini: Inertia memasang `setTimeout(start, delay)` lalu membatalkannya
        // di `inertia:finish`, sementara navigasi backoffice ini selesai dalam
        // 14–21 ms. Nol berarti tiap kunjungan benar-benar memberi umpan balik.
        //
        // Kalau kilatannya di navigasi instan terasa mengganggu, naikkan ke
        // 100–150: di bawah itu manusia belum merasa menunggu.
        delay: 0,

        // Spinner bawaannya duduk di `top: 15px; right: 15px` — tepat menimpa
        // ikon tema dan lonceng di topbar. Bar-nya sudah cukup.
        showSpinner: false,
    },
})
