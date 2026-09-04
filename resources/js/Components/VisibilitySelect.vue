<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'
import { Link } from '@inertiajs/vue3'
import {
    PhArchive,
    PhArrowRight,
    PhCaretDown,
    PhCheck,
    PhEyeSlash,
    PhGlobe,
    PhTimer,
} from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'
import { useI18n } from '@/composables/useI18n'
import { FADE, SPRING_SNAP } from '@/motion'

/**
 * Kolom "Visibility" di daftar berita (`252:4398`) — label berikon dengan caret
 * yang membuka pilihan status.
 *
 * Statusnya diubah DI TEMPAT karena itu yang paling sering dilakukan pada satu
 * artikel setelah ia jadi: menayangkan, menarik kembali dari peredaran,
 * menjadwalkan. Membuka formulir penuh untuk memindahkan satu kata berarti
 * setiap perubahan status ikut membawa risiko mengubah isinya tanpa sadar.
 *
 * `draft` TIDAK ikut, dan itu bukan kelalaian. Ia satu-satunya keadaan yang
 * bukan tujuan melainkan akibat: ia lahir dari tombol "Save Draft" di dalam
 * formulir, bersama isi yang baru saja diketik. Jadi barisnya di menu ini
 * TAUTAN ke formulir itu, bukan pilihan — dan ia cuma muncul pada baris yang
 * memang draft, karena "Edit Draft" pada tulisan yang sudah tayang adalah
 * janji tentang naskah yang tidak ada.
 *
 * Panelnya di-teleport ke `<body>`, alasannya sama persis dengan `RowMenu`:
 * pembungkus `DataTable` memakai `overflow-x-auto`, dan itu mengurung sumbu Y
 * juga — dropdown di baris terakhir akan terpotong.
 */
const props = defineProps<{
    /** Kunci tampilan: `posted` | `scheduled` | `draft` | `unpublished`. */
    value: string
    /** Judul artikelnya — dipakai untuk label pembaca layar. */
    name: string
    /** Formulirnya. Baris "Edit Draft" menuju ke sini, dan tanpa alamat ini ia
     *  tidak digambar sama sekali. */
    editHref?: string
    /** Waktu tayang terjadwal, dicetak di bawah label. */
    scheduledFor?: string | null
    /** `scheduled` hanya bisa dipilih kalau jadwalnya masih di depan. */
    canSchedule?: boolean
    disabled?: boolean
}>()

const emit = defineEmits<{ select: [status: string] }>()

const { t } = useI18n()

/**
 * Empat keadaan yang bisa DITAMPILKAN, tiga yang bisa DIPILIH.
 *
 * `status` adalah yang dikirim ke server; `key` adalah yang dibaca dari server.
 * Keduanya berbeda untuk satu keadaan saja — `posted` ditulis `published` di
 * kolom database — dan pemetaannya dikunci di sini supaya tidak ada yang
 * menebaknya di tempat lain.
 *
 * Pemisahan STATES/SELECTABLE-lah yang membuat baris draft tetap punya ikon dan
 * kata saat dicetak di tombolnya, tanpa ikut jadi tujuan yang bisa diklik.
 * Sisi server memakai daftar yang sama lewat `QUICK_STATUSES`.
 */
const STATES = [
    { key: 'posted', status: 'published', icon: PhGlobe },
    { key: 'scheduled', status: 'scheduled', icon: PhTimer },
    { key: 'draft', status: 'draft', icon: PhArchive },
    { key: 'unpublished', status: 'unpublished', icon: PhEyeSlash },
] as const

const SELECTABLE = STATES.filter((o) => o.key !== 'draft')

const current = computed(() => STATES.find((o) => o.key === props.value) ?? STATES[2])

/** Tautan "Edit Draft" hanya untuk baris yang memang draft. */
const showEditDraft = computed(() => props.value === 'draft' && Boolean(props.editHref))

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const style = ref<Record<string, string>>({})
const flipped = ref(false)

const WIDTH = 200
const MARGIN = 8

function bindPanel(el: Element | ComponentPublicInstance | null): void {
    if (el === null) {
        panel.value = null
        return
    }

    const node = el instanceof HTMLElement ? el : (el as ComponentPublicInstance).$el

    panel.value = node instanceof HTMLElement ? node : null
}

function place(): void {
    const button = root.value?.querySelector('button')
    if (!button) return

    const rect = button.getBoundingClientRect()
    const height = panel.value?.offsetHeight ?? 0
    const below = window.innerHeight - rect.bottom
    const flip = height > 0 && below < height + MARGIN && rect.top > height + MARGIN

    flipped.value = flip

    style.value = {
        position: 'fixed',
        top: flip ? `${rect.top - height - 4}px` : `${rect.bottom + 4}px`,
        left: `${Math.min(Math.max(MARGIN, rect.left), window.innerWidth - WIDTH - MARGIN)}px`,
        width: `${WIDTH}px`,
    }
}

async function toggle(): Promise<void> {
    if (props.disabled) return

    open.value = !open.value

    if (open.value) {
        await nextTick()
        place()
        await nextTick()
        place()
    }
}

function close(): void {
    open.value = false
}

function choose(status: string): void {
    close()
    if (status === current.value.status) return
    emit('select', status)
}

function onDocumentClick(event: MouseEvent): void {
    if (!open.value) return
    const target = event.target as Node
    if (root.value?.contains(target) || panel.value?.contains(target)) return
    close()
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') close()
}

function onReflow(): void {
    if (open.value) place()
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
    window.addEventListener('scroll', onReflow, { passive: true, capture: true })
    window.addEventListener('resize', onReflow, { passive: true })
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('scroll', onReflow, { capture: true })
    window.removeEventListener('resize', onReflow)
})

const scheduleLabel = computed(() => {
    if (!props.scheduledFor) return null
    return new Date(props.scheduledFor).toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
})
</script>

<template>
    <div ref="root" class="flex flex-col gap-1">
        <motion.button
            type="button"
            class="flex w-fit items-center gap-2 rounded px-1 py-1 text-body-s transition-colors"
            :class="
                disabled
                    ? 'cursor-not-allowed text-cool-40'
                    : [
                          'cursor-pointer text-cool-90 hover:bg-cool-10',
                          value === 'draft' ? 'bg-cool-10' : '',
                      ]
            "
            :aria-label="t('news.change_visibility', { name })"
            :aria-expanded="open"
            aria-haspopup="menu"
            :disabled="disabled"
            :while-press="disabled ? undefined : { scale: 0.96 }"
            :transition="SPRING_SNAP"
            @click="toggle"
        >
            <component :is="current.icon" :size="18" aria-hidden="true" class="shrink-0" />
            <span class="whitespace-nowrap">{{ t(`news.status_${current.key}`) }}</span>
            <motion.span
                :animate="{ rotate: open ? 180 : 0 }"
                :transition="SPRING_SNAP"
                class="flex items-center"
            >
                <PhCaretDown :size="16" aria-hidden="true" />
            </motion.span>
        </motion.button>

        <!-- Jadwalnya dicetak di bawah labelnya, bukan di kolom Published:
             kolom itu untuk yang sudah tayang, dan mengisinya dengan waktu yang
             belum terjadi membuat dua baris yang keadaannya berbeda terlihat
             sama. -->
        <span v-if="scheduleLabel" class="pl-1 text-body-xs text-cool-60">
            {{ scheduleLabel }}
        </span>

        <Teleport to="body">
            <AnimatePresence>
                <motion.div
                    v-if="open"
                    key="panel"
                    :ref="bindPanel"
                    role="menu"
                    class="z-50 border border-cool-20 bg-surface py-1 shadow-editor"
                    :style="{ ...style, transformOrigin: flipped ? 'bottom left' : 'top left' }"
                    :initial="{ opacity: 0, scale: 0.94, y: flipped ? 4 : -4 }"
                    :animate="{ opacity: 1, scale: 1, y: 0 }"
                    :exit="{ opacity: 0, scale: 0.96, y: flipped ? 2 : -2 }"
                    :transition="SPRING_SNAP"
                >
                    <button
                        v-for="option in SELECTABLE"
                        :key="option.key"
                        type="button"
                        role="menuitemradio"
                        :aria-checked="option.key === current.key"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left text-body-s transition-colors"
                        :class="
                            option.key === 'scheduled' && !canSchedule && option.key !== current.key
                                ? 'cursor-not-allowed text-cool-40'
                                : 'cursor-pointer text-cool-90 hover:bg-cool-10'
                        "
                        :disabled="option.key === 'scheduled' && !canSchedule && option.key !== current.key"
                        :title="
                            option.key === 'scheduled' && !canSchedule
                                ? t('news.needs_schedule')
                                : undefined
                        "
                        @click="choose(option.status)"
                    >
                        <component :is="option.icon" :size="18" aria-hidden="true" class="shrink-0" />
                        <span class="flex-1">{{ t(`news.status_${option.key}`) }}</span>
                        <motion.span
                            v-if="option.key === current.key"
                            :initial="{ opacity: 0, scale: 0.6 }"
                            :animate="{ opacity: 1, scale: 1 }"
                            :transition="FADE"
                            class="flex items-center text-primary-90"
                        >
                            <PhCheck :size="16" aria-hidden="true" />
                        </motion.span>
                    </button>

                    <!-- Bukan pilihan status: ia membuka formulirnya. Dipisah
                         garis supaya bedanya terlihat sebelum diklik, bukan
                         sesudah halamannya berpindah. -->
                    <template v-if="showEditDraft">
                        <div class="my-1 border-t border-cool-20" role="separator" />
                        <Link
                            :href="editHref!"
                            role="menuitem"
                            class="flex w-full cursor-pointer items-center gap-2 px-3 py-2 text-left text-body-s text-cool-90 transition-colors hover:bg-cool-10"
                            @click="close"
                        >
                            <PhArchive :size="18" aria-hidden="true" class="shrink-0" />
                            <span class="flex-1">{{ t('news.edit_draft') }}</span>
                            <PhArrowRight :size="16" aria-hidden="true" class="shrink-0 text-cool-60" />
                        </Link>
                    </template>
                </motion.div>
            </AnimatePresence>
        </Teleport>
    </div>
</template>
