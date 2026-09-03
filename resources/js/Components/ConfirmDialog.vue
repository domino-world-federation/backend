<script setup lang="ts">
import { ref, watch, nextTick } from 'vue'
import { PhInfo, PhTrash } from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'
import AppButton from '@/Components/AppButton.vue'
import { FADE, SPRING_SNAP } from '@/motion'

/**
 * Component set Figma `Confirmation` (`330:8004`) — dua varian, Type=info dan
 * Type=deletion. Kartu 544 lebar, padding 32/32/24, gap 32, radius 12.
 *
 * `<dialog>` bawaan browser, bukan div ber-overlay: jebakan fokus, Escape, dan
 * `inert` untuk latar belakang semuanya sudah benar tanpa ditulis — dan
 * menulisnya sendiri hampir selalu setengah benar.
 *
 * TAPI `<dialog>` itu direntangkan SELAYAR PENUH dan dibuat transparan, lalu
 * isinya ditengahkan sendiri dengan flex. Dua sebabnya:
 *
 * 1. Preflight Tailwind menyetel `margin: 0` ke semua elemen, dan itu menghapus
 *    `margin: auto` dari stylesheet bawaan browser — justru satu-satunya hal
 *    yang menengahkan `<dialog>`. Gejalanya: modal menempel di kiri atas layar.
 *    Menambal dengan `m-auto` bisa, tapi tetap menyisakan masalah kedua.
 * 2. `::backdrop` adalah pseudo-element; Motion tidak bisa menganimasikannya.
 *    Dengan menggambar scrim sendiri sebagai elemen sungguhan, pudar-masuk dan
 *    pudar-keluarnya ikut dikendalikan bersama kartunya.
 */
const props = withDefaults(
    defineProps<{
        open: boolean
        variant?: 'info' | 'deletion'
        title: string
        description?: string
        confirmLabel?: string
        cancelLabel?: string
        processing?: boolean
    }>(),
    {
        variant: 'info',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        processing: false,
    },
)

const emit = defineEmits<{ confirm: []; cancel: [] }>()

const dialog = ref<HTMLDialogElement | null>(null)

/**
 * Terpisah dari prop `open` — dan itu memang intinya.
 *
 * `showModal()` harus dipanggil SEBELUM isinya dipasang (dialog yang tertutup
 * `display: none`, jadi tidak ada yang bisa diukur), sementara `close()` harus
 * ditunda sampai animasi keluar selesai. Satu penanda yang mengikuti keduanya
 * mustahil; jadi `visible` yang menyalakan animasi, dan elemen dialognya dibuka
 * lebih dulu serta ditutup belakangan.
 */
const visible = ref(false)

watch(
    () => props.open,
    async (open) => {
        if (open) {
            await nextTick()
            if (dialog.value && !dialog.value.open) dialog.value.showModal()
            visible.value = true
            return
        }

        visible.value = false
    },
    { immediate: true },
)

/** Dipanggil `AnimatePresence` setelah kartu benar-benar habis dianimasikan. */
function afterExit(): void {
    if (dialog.value?.open) dialog.value.close()
}

/**
 * Hanya diteruskan kalau browser yang menutup (Escape lewat jalur lain).
 *
 * Tanpa penjagaan ini, `close()` yang KITA panggil di `afterExit()` akan
 * memancing `cancel` sekali lagi — termasuk sesudah orang menekan Confirm.
 */
function onNativeClose(): void {
    if (props.open) emit('cancel')
}

function requestCancel(): void {
    if (props.processing) return
    emit('cancel')
}
</script>

<template>
    <dialog
        ref="dialog"
        class="fixed inset-0 m-0 h-full max-h-full w-full max-w-full overflow-visible bg-transparent p-0 backdrop:bg-transparent"
        @cancel.prevent="requestCancel"
        @close="onNativeClose"
    >
        <AnimatePresence @exit-complete="afterExit">
            <motion.div
                v-if="visible"
                key="scrim"
                class="fixed inset-0 flex items-center justify-center overflow-y-auto bg-black/40 p-4"
                :initial="{ opacity: 0 }"
                :animate="{ opacity: 1 }"
                :exit="{ opacity: 0 }"
                :transition="FADE"
                @click.self="requestCancel"
            >
                <!--
                    Naik sedikit saat masuk dan turun saat keluar: arah yang
                    berbeda membuat "muncul" dan "hilang" terbaca beda tanpa
                    perlu durasi yang berbeda.
                -->
                <motion.div
                    key="card"
                    role="document"
                    class="w-[544px] max-w-full rounded-xl bg-cool-10 shadow-editor"
                    :initial="{ opacity: 0, scale: 0.94, y: 16 }"
                    :animate="{ opacity: 1, scale: 1, y: 0 }"
                    :exit="{ opacity: 0, scale: 0.97, y: 8 }"
                    :transition="SPRING_SNAP"
                >
                    <div class="flex flex-col items-center gap-8 px-8 pt-8 pb-6">
                        <div class="flex flex-col items-center gap-4 text-center">
                            <!--
                                Ikonnya masuk belakangan dan sedikit membesar —
                                50 ms cukup untuk membuat mata mendarat di sana
                                lebih dulu, dan di dialog hapus itu justru yang
                                harus dibaca duluan.
                            -->
                            <motion.span
                                :initial="{ opacity: 0, scale: 0.6 }"
                                :animate="{ opacity: 1, scale: 1 }"
                                :transition="{ ...SPRING_SNAP, delay: 0.05 }"
                                :class="variant === 'deletion' ? 'text-danger' : 'text-primary-90'"
                            >
                                <component
                                    :is="variant === 'deletion' ? PhTrash : PhInfo"
                                    :size="48"
                                    aria-hidden="true"
                                />
                            </motion.span>
                            <h2 class="text-heading-6 text-cool-90">{{ title }}</h2>
                            <p v-if="description" class="text-body-s text-cool-60">
                                {{ description }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <AppButton variant="outline" :disabled="processing" @click="requestCancel">
                                {{ cancelLabel }}
                            </AppButton>
                            <AppButton :disabled="processing" @click="emit('confirm')">
                                {{ confirmLabel }}
                            </AppButton>
                        </div>
                    </div>
                </motion.div>
            </motion.div>
        </AnimatePresence>
    </dialog>
</template>
