<script setup lang="ts">
import { motion } from 'motion-v'
import Skeleton from '@/Components/Skeleton.vue'
import { FADE } from '@/motion'

/**
 * Bentuk tunggu untuk `DataTable`. Rangka luarnya sengaja sama persis — border
 * CoolGray/20, header CoolGray/10, sel padding 16×12 — supaya saat datanya
 * datang tabelnya tidak melompat.
 *
 * Lebar tiap sel divariasikan supaya terbaca sebagai teks, bukan sebagai garis
 * seragam yang justru terlihat seperti komponen rusak.
 *
 * Ia memudar MASUK, bukan langsung ada. Filter yang dijawab dalam 30 ms akan
 * memunculkan lalu membuang rangka ini dalam satu kedipan; pudar 180 ms membuat
 * kedipan itu tidak pernah sempat terbentuk. Tidak ada `exit` di sini —
 * pasangannya (`DataTable`) juga memudar masuk, dan keduanya bersaudara di
 * `v-if`/`v-else` tanpa `AnimatePresence` di atasnya.
 */
withDefaults(
    defineProps<{
        columns: number
        rows?: number
        /** Diumumkan pembaca layar selama menunggu. */
        label?: string
    }>(),
    { rows: 5, label: 'Memuat data…' },
)

const WIDTHS = ['w-32', 'w-20', 'w-40', 'w-24', 'w-16', 'w-28']
</script>

<template>
    <motion.div
        class="w-full border border-cool-20 bg-surface"
        :initial="{ opacity: 0 }"
        :animate="{ opacity: 1 }"
        :transition="FADE"
        role="status"
        aria-busy="true"
        :aria-label="label"
    >
        <div class="flex border-t border-cool-20 bg-cool-10">
            <div v-for="col in columns" :key="`h-${col}`" class="flex-1 px-3 py-4">
                <Skeleton class="h-3.5 w-24" />
            </div>
        </div>

        <div v-for="row in rows" :key="`r-${row}`" class="flex border-t border-cool-20">
            <div v-for="col in columns" :key="`c-${row}-${col}`" class="flex-1 px-3 py-4">
                <Skeleton class="h-3.5" :class="WIDTHS[(row + col) % WIDTHS.length]" />
            </div>
        </div>
    </motion.div>
</template>
