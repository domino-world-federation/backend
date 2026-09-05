<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { PhMoon, PhSun } from '@phosphor-icons/vue'
import AppSidebar from '@/Components/Sidebar/AppSidebar.vue'
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import { useI18n } from '@/composables/useI18n'
import { useTheme } from '@/composables/useTheme'
import type { SharedProps } from '@/types'

/**
 * Kerangka tiap halaman backoffice: sidebar 312px di kiri, topbar 56px putih,
 * kanvas #E8E8E8 dengan kolom konten 1080px bergutter 24px — Figma `251:843`
 * dan saudara-saudaranya.
 */
const page = usePage<SharedProps>()
const { theme, toggle } = useTheme()
const { t, localeSwitchable } = useI18n()

const COLLAPSE_KEY = 'dwf.sidebar.collapsed'
const collapsed = ref(false)

// Dibaca setelah mount, bukan saat setup: `localStorage` tidak ada saat render
// server-side, dan membacanya di sana akan meledak begitu SSR dinyalakan.
onMounted(() => {
    try {
        collapsed.value = localStorage.getItem(COLLAPSE_KEY) === 'true'
    } catch {
        // Penyimpanan diblokir — sidebar mulai terbuka, itu bawaannya.
    }
})

watch(collapsed, (value) => {
    try {
        localStorage.setItem(COLLAPSE_KEY, String(value))
    } catch {
        // idem
    }
})
</script>

<template>
    <div class="flex min-h-screen bg-shell">
        <AppSidebar v-model:collapsed="collapsed" />

        <div class="flex min-w-0 flex-1 flex-col bg-canvas">
            <!-- Ikut dipatok seperti sidebar. Sidebar diam sementara topbar
                 hanyut ke atas terbaca sebagai kerangka yang copot, bukan
                 sebagai pilihan. -->
            <header
                class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-end gap-4 bg-surface px-6"
            >
                <LanguageSwitcher v-if="localeSwitchable" />

                <button
                    type="button"
                    class="cursor-pointer text-cool-90"
                    :aria-label="theme === 'dark' ? t('nav.theme_dark') : t('nav.theme_light')"
                    @click="toggle"
                >
                    <component :is="theme === 'dark' ? PhMoon : PhSun" :size="24" aria-hidden="true" />
                </button>

                <!-- Hidup sejak 2026-09-05. Tombol ini dulu `disabled` dengan
                     tooltip yang mengakui alasannya — belum ada yang mengirim
                     notifikasi — dan tooltip itu dihapus bersama alasannya,
                     bukan dibiarkan menua jadi keterangan yang salah. -->
                <NotificationBell />
            </header>

            <!--
                Kolom konten RATA KIRI, bukan di tengah.

                Figma menggambar kolom 1080px di dalam area konten 1128px pada
                frame 1440px — jadi gutter-nya 24px, dan di lebar itu "di tengah"
                dan "rata kiri" kebetulan sama. Di layar yang lebih lebar keduanya
                berpisah: `mx-auto` membuang seluruh kelebihan ruang jadi lubang
                kosong di kiri (322px di 1800px dengan sidebar ciut), dan yang
                terburuk, MENCIUTKAN sidebar untuk dapat ruang justru MELEBARKAN
                lubang itu — ruang yang didapat langsung masuk ke gutter.

                Batasnya dinaikkan 1080 -> 1440: tabel News List punya tujuh kolom
                dan 1080px membuat tiap sel tanggal pecah jadi dua baris.
            -->
            <main class="flex-1 px-6 py-5">
                <div class="flex w-full max-w-[1440px] flex-col gap-4">
                    <!-- Flash dipasang di layout, bukan tiap halaman: setiap
                         modul nanti mengirimnya lewat session, dan menempelkan
                         banner di 18 halaman berarti 18 kesempatan lupa. -->
                    <p
                        v-if="page.props.flash.success"
                        role="status"
                        class="border-l-4 border-primary-60 bg-surface px-4 py-3 text-body-s text-cool-90"
                    >
                        {{ page.props.flash.success }}
                    </p>
                    <p
                        v-if="page.props.flash.error"
                        role="alert"
                        class="border-l-4 border-danger bg-surface px-4 py-3 text-body-s text-cool-90"
                    >
                        {{ page.props.flash.error }}
                    </p>

                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
