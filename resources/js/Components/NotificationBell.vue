<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { PhBell } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'
import type { SharedProps } from '@/types'

/**
 * Lonceng di topbar.
 *
 * Sampai 2026-09-05 tombol ini mati, dengan tooltip yang mengakui alasannya:
 * belum ada yang mengirim notifikasi. Sekarang ada — pesan kontak dan laporan
 * integritas dari situs publik, plus ringkasan buletin harian.
 *
 * **Menekan satu baris MEMBUKA barisnya, bukan sekadar menandainya terbaca.**
 * Dua aksi terpisah berarti panel yang penuh hal-hal yang sebenarnya sudah
 * dikerjakan orangnya, dan sesudah itu angkanya berhenti berarti apa-apa. Yang
 * menandai terbaca adalah server, di rute yang sama yang mengalihkannya ke
 * tujuannya — jadi ia tetap benar walau tautannya dibuka di tab baru.
 *
 * Titiknya ada di baris yang belum dibaca, DAN judulnya lebih tebal. Warna
 * sendirian tidak cukup untuk membedakan dua keadaan.
 */
const page = usePage<SharedProps>()
const { t } = useI18n()

const open = ref(false)
const root = ref<HTMLElement | null>(null)

const bell = computed(() => page.props.notifications)
const items = computed(() => bell.value?.items ?? [])
const unread = computed(() => bell.value?.unreadCount ?? 0)

function close(): void {
    open.value = false
}

function markAll(): void {
    close()
    router.post('/notifications/read-all', {}, { preserveScroll: true })
}

function onDocumentClick(event: MouseEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) close()
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') close()
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative cursor-pointer text-cool-90"
            :aria-label="
                unread > 0
                    ? `${t('nav.notifications')} — ${t('nav.notifications_count', { count: unread })}`
                    : t('nav.notifications')
            "
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="open = !open"
        >
            <PhBell :size="24" aria-hidden="true" />

            <!-- Angkanya dicetak, bukan titik polos: "ada sesuatu" dan "ada
                 sebelas hal" adalah dua keadaan yang berbeda tindakannya.
                 Berhenti di 9+ karena lebih dari itu tidak muat di atas ikon
                 24px, dan angka pastinya sudah ada di sidebar. -->
            <span
                v-if="unread > 0"
                aria-hidden="true"
                class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] leading-none font-semibold text-white"
            >
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div
            v-if="open"
            role="menu"
            class="absolute top-full right-0 z-30 mt-1 w-[320px] border border-cool-20 bg-surface shadow-editor"
        >
            <div class="flex items-center justify-between gap-2 border-b border-cool-20 px-4 py-2">
                <p class="text-body-xs font-semibold text-cool-90">
                    {{ t('nav.notifications') }}
                </p>
                <button
                    v-if="unread > 0"
                    type="button"
                    class="cursor-pointer text-body-xs text-primary-60 hover:underline"
                    @click="markAll"
                >
                    {{ t('nav.notifications_mark_all') }}
                </button>
            </div>

            <p v-if="items.length === 0" class="px-4 py-6 text-body-xs text-cool-70">
                {{ t('nav.notifications_empty') }}
            </p>

            <!-- `<a>` biasa, bukan `<Link>` Inertia: rutenya menandai terbaca
                 lalu MENGALIHKAN, dan kunjungan Inertia yang berakhir di
                 redirect ke halaman lain harus melewati respons yang bukan
                 Inertia. Navigasi penuh di sini juga yang membuat "buka di tab
                 baru" bekerja seperti yang orang harapkan dari sebuah daftar. -->
            <a
                v-for="item in items"
                :key="item.id"
                role="menuitem"
                :href="`/notifications/${item.id}`"
                class="flex gap-2 border-b border-cool-10 px-4 py-3 last:border-b-0 hover:bg-cool-10"
            >
                <span
                    aria-hidden="true"
                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                    :class="item.isRead ? 'bg-transparent' : 'bg-primary-60'"
                />
                <span class="min-w-0">
                    <span
                        class="block truncate text-body-xs text-cool-90"
                        :class="item.isRead ? 'font-normal' : 'font-semibold'"
                    >
                        {{ item.title }}
                    </span>
                    <span class="block truncate text-body-xs text-cool-70">{{ item.body }}</span>
                </span>
            </a>
        </div>
    </div>
</template>
