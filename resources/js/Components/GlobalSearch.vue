<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { PhMagnifyingGlass, PhX } from '@phosphor-icons/vue'

import { useI18n } from '@/composables/useI18n'

/**
 * Kotak pencarian lintas modul di topbar.
 *
 * **Tidak ada di wireframe.** Topbar yang digambar (`251:1212`) cuma matahari
 * dan lonceng; kotak "Search" yang ada di desain adalah penyaring DI DALAM
 * halaman daftar. Penambahan atas permintaan pemilik repo 2026-09-10, dicatat
 * sebagai penyimpangan di `docs/PROGRESS.md`.
 *
 * **`fetch`, bukan kunjungan Inertia.** Hasilnya panel melayang di atas halaman
 * yang sedang dibaca orang; kunjungan Inertia akan MENGGANTI halaman itu, jadi
 * mengetik di kotak pencarian berarti kehilangan tempat. Karena `fetch` tidak
 * mengirim header CSRF sendiri, ia dibaca dari `<meta name="csrf-token">` —
 * jebakan yang sama dengan unggahan gambar editor, dan tercatat di CONVENTIONS.
 * (Untuk `GET` ia tidak wajib; dikirim tetap supaya menyalin pola ini untuk
 * endpoint yang menulis tidak menghasilkan 419 yang membingungkan.)
 *
 * **Ditunda 250 ms.** Tanpa itu tiap huruf menghasilkan satu permintaan berisi
 * delapan query — mengetik "tournament" jadi sepuluh kali itu.
 */
interface Item {
    id: number | string
    label: string
    note: string | null
    href: string
}

interface Group {
    module: string
    label: string
    items: Item[]
}

const MIN_LENGTH = 2
const DEBOUNCE_MS = 250

const { t } = useI18n()

const term = ref('')
const groups = ref<Group[]>([])
const busy = ref(false)
const open = ref(false)
const root = ref<HTMLElement | null>(null)
const input = ref<HTMLInputElement | null>(null)

let timer: ReturnType<typeof setTimeout> | undefined
/*
 * Jawaban yang datang terlambat DIBUANG.
 *
 * Dua permintaan yang berangkat berurutan tidak dijamin kembali berurutan, jadi
 * tanpa penanda ini hasil untuk "tou" bisa mendarat sesudah hasil untuk
 * "tournament" dan menimpanya — panel memperlihatkan jawaban atas kata yang
 * sudah tidak ada lagi di kotaknya.
 */
let sequence = 0

const flat = computed(() => groups.value.flatMap((group) => group.items))
const hasResults = computed(() => flat.value.length > 0)

watch(term, (value) => {
    clearTimeout(timer)

    if (value.trim().length < MIN_LENGTH) {
        groups.value = []
        busy.value = false
        return
    }

    busy.value = true
    timer = setTimeout(() => void look(value.trim()), DEBOUNCE_MS)
})

async function look(value: string): Promise<void> {
    const ticket = ++sequence

    try {
        const response = await fetch(`/search?q=${encodeURIComponent(value)}`, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        })

        if (ticket !== sequence) return

        groups.value = response.ok ? ((await response.json()).groups ?? []) : []
    } catch {
        // Jaringan putus atau sesi habis. Panel kosong dengan kalimat "tidak
        // ada yang cocok" lebih jujur daripada spinner yang berputar selamanya.
        if (ticket === sequence) groups.value = []
    } finally {
        if (ticket === sequence) busy.value = false
    }
}

function go(item: Item): void {
    close()
    router.visit(item.href)
}

function close(): void {
    open.value = false
    term.value = ''
    groups.value = []
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close()
        return
    }

    // Enter membuka hasil PERTAMA. Tidak ada halaman "semua hasil" untuk
    // dituju, jadi Enter yang tidak melakukan apa-apa akan terbaca sebagai
    // pencarian yang menggantung.
    if (event.key === 'Enter' && hasResults.value) {
        event.preventDefault()
        go(flat.value[0]!)
    }
}

function onDocumentPointerDown(event: PointerEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) close()
}

/**
 * `Cmd/Ctrl + K` membuka kotaknya.
 *
 * Pintasan yang sudah jadi kebiasaan lintas aplikasi. Ia mendengarkan di
 * dokumen dan sengaja TIDAK aktif saat orang sedang mengetik di kontrol lain —
 * merebut fokus dari tengah sebuah formulir adalah cara kehilangan kalimat yang
 * belum selesai.
 */
function onShortcut(event: KeyboardEvent): void {
    if (event.key.toLowerCase() !== 'k' || !(event.metaKey || event.ctrlKey)) return

    const active = document.activeElement
    const typing =
        active instanceof HTMLInputElement ||
        active instanceof HTMLTextAreaElement ||
        (active instanceof HTMLElement && active.isContentEditable)

    if (typing && active !== input.value) return

    event.preventDefault()
    open.value = true
    void nextTick(() => input.value?.focus())
}

onMounted(() => {
    document.addEventListener('pointerdown', onDocumentPointerDown)
    document.addEventListener('keydown', onShortcut)
})

onBeforeUnmount(() => {
    clearTimeout(timer)
    document.removeEventListener('pointerdown', onDocumentPointerDown)
    document.removeEventListener('keydown', onShortcut)
})
</script>

<template>
    <div ref="root" class="relative">
        <label class="flex items-center gap-2 rounded bg-cool-10 px-3 py-1.5">
            <PhMagnifyingGlass :size="18" class="shrink-0 text-cool-60" aria-hidden="true" />
            <span class="sr-only">{{ t('nav.search') }}</span>
            <input
                ref="input"
                v-model="term"
                type="search"
                :placeholder="t('nav.search_placeholder')"
                class="w-40 bg-transparent text-body-s text-cool-90 outline-none placeholder:text-cool-60 focus:w-64 lg:w-56 lg:focus:w-80"
                autocomplete="off"
                @focus="open = true"
                @keydown="onKeydown"
            />
            <button
                v-if="term !== ''"
                type="button"
                class="shrink-0 cursor-pointer text-cool-60 hover:text-cool-90"
                :aria-label="t('common.clear')"
                @click="close"
            >
                <PhX :size="16" aria-hidden="true" />
            </button>
        </label>

        <!-- `absolute` di dalam topbar aman: topbar tidak `overflow-hidden`,
             tidak seperti pembungkus `DataTable` yang memaksa `RowMenu` pakai
             `Teleport`. -->
        <div
            v-if="open && term.trim().length > 0"
            class="absolute right-0 z-30 mt-2 max-h-[70vh] w-[22rem] overflow-y-auto rounded border border-cool-20 bg-surface py-2 shadow-lg"
        >
            <p v-if="term.trim().length < 2" class="px-4 py-2 text-body-s text-cool-60">
                {{ t('nav.search_hint') }}
            </p>
            <p v-else-if="busy" class="px-4 py-2 text-body-s text-cool-60">
                {{ t('nav.search_busy') }}
            </p>
            <p v-else-if="!hasResults" class="px-4 py-2 text-body-s text-cool-60">
                {{ t('nav.search_empty') }}
            </p>

            <template v-else>
                <div v-for="group in groups" :key="group.module + group.label" class="py-1">
                    <p class="px-4 py-1 text-body-xs text-cool-60 uppercase">{{ group.label }}</p>

                    <button
                        v-for="item in group.items"
                        :key="`${group.label}-${item.id}`"
                        type="button"
                        class="flex w-full cursor-pointer flex-col items-start gap-0.5 px-4 py-2 text-left hover:bg-cool-10 focus-visible:bg-cool-10 focus-visible:outline-none"
                        @click="go(item)"
                    >
                        <span class="line-clamp-2 text-body-s text-cool-90">{{ item.label }}</span>
                        <span v-if="item.note" class="text-body-xs text-cool-60">{{ item.note }}</span>
                    </button>
                </div>
            </template>
        </div>
    </div>
</template>
