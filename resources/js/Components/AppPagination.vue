<script setup lang="ts">
import { computed, useId } from 'vue'
import { PhCaretLeft, PhCaretRight } from '@phosphor-icons/vue'
import { motion } from 'motion-v'
import { useI18n } from '@/composables/useI18n'
import { SPRING_SNAP, SPRING_SOFT } from '@/motion'

/**
 * Komponen Figma `Pagination` (`252:2170`): Previous · 1 2 3 4 5 … 11 · Next,
 * halaman aktif berlatar Primary/60.
 *
 * Deretan angkanya dihitung, bukan disalin dari desain: wireframe menggambar
 * satu keadaan (11 halaman, halaman 2 aktif) dan kalau bentuknya di-hardcode,
 * daftar berisi tiga halaman akan menampilkan tombol ke halaman yang tidak ada.
 */
const props = defineProps<{
    currentPage: number
    lastPage: number
    /** Diberi nomor halaman, mengembalikan URL-nya. */
    hrefFor: (page: number) => string
}>()

const { t } = useI18n()

const emit = defineEmits<{ navigate: [page: number] }>()

/**
 * Pil emas itu SATU elemen yang berpindah, bukan latar yang mati di sini dan
 * menyala di sana.
 *
 * `layoutId` yang sama pada dua elemen berbeda membuat Motion menganimasikan
 * yang satu ke posisi yang lain saat pemiliknya berganti. Hasilnya penanda
 * halaman aktif meluncur mengikuti arah klik — dan arah itu sendiri yang
 * memberi tahu "kamu maju", tanpa perlu tulisan.
 *
 * Id-nya diberi awalan `useId()` supaya dua pagination di satu layar tidak
 * saling menarik pil masing-masing melintasi halaman.
 */
const uid = useId()

/** `1 … 4 5 6 … 11` — selalu halaman pertama, terakhir, dan tetangga aktif. */
const pages = computed<Array<number | 'gap'>>(() => {
    const { currentPage, lastPage } = props
    if (lastPage <= 7) {
        return Array.from({ length: lastPage }, (_, i) => i + 1)
    }

    const window = new Set([1, lastPage, currentPage, currentPage - 1, currentPage + 1])
    const visible = [...window].filter((p) => p >= 1 && p <= lastPage).sort((a, b) => a - b)

    const out: Array<number | 'gap'> = []
    let previous = 0
    for (const page of visible) {
        if (previous && page - previous > 1) out.push('gap')
        out.push(page)
        previous = page
    }

    return out
})

const itemClass = 'flex h-10 items-center justify-center px-2 text-button-m transition-colors'
</script>

<template>
    <nav v-if="lastPage > 1" :aria-label="t('nav.pagination')" class="flex items-center justify-center">
        <a
            :class="[
                itemClass,
                currentPage <= 1
                    ? 'pointer-events-none text-cool-40'
                    : 'text-cool-60 hover:text-cool-90',
            ]"
            :href="currentPage > 1 ? hrefFor(currentPage - 1) : undefined"
            :aria-disabled="currentPage <= 1 ? 'true' : undefined"
            @click.prevent="currentPage > 1 && emit('navigate', currentPage - 1)"
        >
            <!-- Caret bergeser ke arah tujuannya saat disentuh: isyarat arah
                 yang tidak memerlukan satu kata pun tambahan. -->
            <motion.span
                :while-hover="currentPage > 1 ? { x: -3 } : undefined"
                :transition="SPRING_SNAP"
                class="flex items-center"
            >
                <PhCaretLeft :size="24" aria-hidden="true" />
            </motion.span>
            <span class="px-2">Previous</span>
        </a>

        <template v-for="(page, index) in pages" :key="`${page}-${index}`">
            <span v-if="page === 'gap'" :class="[itemClass, 'text-cool-100']" aria-hidden="true">
                …
            </span>
            <a
                v-else
                :class="[
                    itemClass,
                    'relative px-4',
                    page === currentPage ? 'text-primary-90' : 'text-cool-100 hover:bg-cool-10',
                ]"
                :href="hrefFor(page)"
                :aria-current="page === currentPage ? 'page' : undefined"
                @click.prevent="emit('navigate', page)"
            >
                <motion.span
                    v-if="page === currentPage"
                    :layout-id="`${uid}-active-page`"
                    :transition="SPRING_SOFT"
                    class="absolute inset-0 border-2 border-primary-60 bg-primary-60"
                    aria-hidden="true"
                />
                <!--
                    Umpan balik tekan menempel di ANGKANYA, bukan di `<a>`-nya.
                    Kalau `<a>` yang diperkecil, ia jadi induk ber-transform bagi
                    pil `layoutId` di dalamnya, dan pengukuran layout Motion
                    membaca posisi yang sudah tergeser — pilnya meleset sesaat
                    tiap kali halaman diklik.
                -->
                <motion.span
                    class="relative"
                    :while-press="{ scale: 0.88 }"
                    :transition="SPRING_SNAP"
                >
                    {{ page }}
                </motion.span>
            </a>
        </template>

        <a
            :class="[
                itemClass,
                currentPage >= lastPage
                    ? 'pointer-events-none text-cool-40'
                    : 'text-cool-100 hover:text-cool-90',
            ]"
            :href="currentPage < lastPage ? hrefFor(currentPage + 1) : undefined"
            :aria-disabled="currentPage >= lastPage ? 'true' : undefined"
            @click.prevent="currentPage < lastPage && emit('navigate', currentPage + 1)"
        >
            <span class="px-2">Next</span>
            <motion.span
                :while-hover="currentPage < lastPage ? { x: 3 } : undefined"
                :transition="SPRING_SNAP"
                class="flex items-center"
            >
                <PhCaretRight :size="24" aria-hidden="true" />
            </motion.span>
        </a>
    </nav>
</template>
