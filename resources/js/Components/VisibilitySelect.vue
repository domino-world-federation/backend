<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'
import {
    PhArchive,
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
 * artikel setelah ia jadi: menayangkan, menarik kembali, mengembalikan ke draft.
 * Membuka formulir penuh untuk memindahkan satu kata berarti setiap perubahan
 * status ikut membawa risiko mengubah isinya tanpa sadar.
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
    /** Waktu tayang terjadwal, dicetak di bawah label. */
    scheduledFor?: string | null
    /** `scheduled` hanya bisa dipilih kalau jadwalnya masih di depan. */
    canSchedule?: boolean
    disabled?: boolean
}>()

const emit = defineEmits<{ select: [status: string] }>()

const { t } = useI18n()

/**
 * Empat keadaan, empat ikon, empat kata.
 *
 * `status` adalah yang dikirim ke server; `key` adalah yang dibaca dari server.
 * Keduanya berbeda untuk satu keadaan saja — `posted` ditulis `published` di
 * kolom database — dan pemetaannya dikunci di sini supaya tidak ada yang
 * menebaknya di tempat lain.
 */
const OPTIONS = [
    { key: 'posted', status: 'published', icon: PhGlobe },
    { key: 'scheduled', status: 'scheduled', icon: PhTimer },
    { key: 'draft', status: 'draft', icon: PhArchive },
    { key: 'unpublished', status: 'unpublished', icon: PhEyeSlash },
] as const

const current = computed(() => OPTIONS.find((o) => o.key === props.value) ?? OPTIONS[2])

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
            aria-haspopup="listbox"
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
                    role="listbox"
                    class="z-50 border border-cool-20 bg-surface py-1 shadow-editor"
                    :style="{ ...style, transformOrigin: flipped ? 'bottom left' : 'top left' }"
                    :initial="{ opacity: 0, scale: 0.94, y: flipped ? 4 : -4 }"
                    :animate="{ opacity: 1, scale: 1, y: 0 }"
                    :exit="{ opacity: 0, scale: 0.96, y: flipped ? 2 : -2 }"
                    :transition="SPRING_SNAP"
                >
                    <button
                        v-for="option in OPTIONS"
                        :key="option.key"
                        type="button"
                        role="option"
                        :aria-selected="option.key === current.key"
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
                </motion.div>
            </AnimatePresence>
        </Teleport>
    </div>
</template>
