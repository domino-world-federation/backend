<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, type ComponentPublicInstance } from 'vue'
import { PhDotsThreeOutline } from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'
import { SPRING_SNAP } from '@/motion'

/**
 * Menu titik-tiga di ujung baris tabel (`253:7846`).
 *
 * Menunya di-TELEPORT ke `<body>` dan diposisikan `fixed`.
 *
 * Sebelumnya ia `absolute` di dalam sel, dan terpotong: pembungkus `DataTable`
 * memakai `overflow-x-auto` untuk tabel lebar, dan menyetel `overflow-x` ke
 * nilai selain `visible` memaksa `overflow-y` jadi `auto` juga — jadi menu
 * yang jatuh di bawah tepi tabel ikut terkurung. Yang terlihat: separuh kata
 * "Edit", persis di baris terakhir.
 *
 * Konsekuensinya posisinya harus dihitung sendiri dan diperbarui saat halaman
 * digulir — elemen `fixed` tidak ikut bergerak bersama isinya.
 */
defineProps<{ label: string }>()

const open = ref(false)
const root = ref<HTMLElement | null>(null)
const panel = ref<HTMLElement | null>(null)
const style = ref<Record<string, string>>({})

/**
 * Menu tumbuh dari SUDUT tempat ia menempel, bukan dari tengahnya.
 *
 * Kalau titik jangkarnya salah, panelnya seolah melompat menjauh dari tombol
 * yang baru ditekan — dan mata membaca itu sebagai "ada dua benda", bukan
 * "tombol ini membuka menu itu".
 */
const flipped = ref(false)

const WIDTH = 160
const MARGIN = 8

/**
 * `ref` di komponen memberi INSTANCE-nya, bukan elemennya — dan `motion.div`
 * adalah komponen. Tanpa membuka `$el`, `panel.offsetHeight` selalu `undefined`
 * dan menunya berhenti tahu kapan harus membuka ke atas.
 */
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

    // Membuka ke ATAS kalau ruang di bawah tidak cukup — baris terakhir tabel
    // hampir selalu dekat tepi bawah viewport.
    const below = window.innerHeight - rect.bottom
    const flip = height > 0 && below < height + MARGIN && rect.top > height + MARGIN

    flipped.value = flip

    style.value = {
        position: 'fixed',
        top: flip ? `${rect.top - height - 4}px` : `${rect.bottom + 4}px`,
        // Rata kanan dengan tombolnya, tapi tidak pernah keluar layar kiri.
        left: `${Math.max(MARGIN, rect.right - WIDTH)}px`,
        width: `${WIDTH}px`,
    }
}

async function toggle(): Promise<void> {
    open.value = !open.value

    if (open.value) {
        // Dua kali: sekali untuk mendapat tinggi panelnya, sekali lagi setelah
        // tingginya diketahui supaya keputusan membalik ke atas benar.
        await nextTick()
        place()
        await nextTick()
        place()
    }
}

function close(): void {
    open.value = false
}

function onDocumentClick(event: MouseEvent): void {
    const target = event.target as Node
    if (!open.value) return
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
    // `capture: true` supaya gulir di dalam pembungkus tabel ikut tertangkap —
    // event scroll tidak menggelembung ke window.
    window.addEventListener('scroll', onReflow, { passive: true, capture: true })
    window.addEventListener('resize', onReflow, { passive: true })
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
    window.removeEventListener('scroll', onReflow, { capture: true })
    window.removeEventListener('resize', onReflow)
})
</script>

<template>
    <div ref="root" class="flex justify-end">
        <motion.button
            type="button"
            class="flex size-8 cursor-pointer items-center justify-center text-cool-60 transition-colors hover:text-cool-90"
            :aria-label="label"
            :aria-expanded="open"
            aria-haspopup="menu"
            :while-hover="{ scale: 1.15 }"
            :while-press="{ scale: 0.9 }"
            :transition="SPRING_SNAP"
            @click="toggle"
        >
            <!--
                `DotsThreeOutline` + `fill` = tiga titik penuh.
                `DotsThree` + `fill` TIDAK: Phosphor menggambarnya sebagai kotak
                membulat terisi dengan titiknya di-knockout — bentuk badge,
                bukan tombol menu.
            -->
            <PhDotsThreeOutline :size="20" weight="fill" aria-hidden="true" />
        </motion.button>

        <Teleport to="body">
            <AnimatePresence>
                <motion.div
                    v-if="open"
                    key="panel"
                    :ref="bindPanel"
                    role="menu"
                    class="z-50 border border-cool-20 bg-surface py-1 shadow-editor"
                    :style="{ ...style, transformOrigin: flipped ? 'bottom right' : 'top right' }"
                    :initial="{ opacity: 0, scale: 0.94, y: flipped ? 4 : -4 }"
                    :animate="{ opacity: 1, scale: 1, y: 0 }"
                    :exit="{ opacity: 0, scale: 0.96, y: flipped ? 2 : -2 }"
                    :transition="SPRING_SNAP"
                    @click="close"
                >
                    <slot />
                </motion.div>
            </AnimatePresence>
        </Teleport>
    </div>
</template>
