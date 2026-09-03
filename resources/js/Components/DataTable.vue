<script setup lang="ts" generic="T extends object">
import { AnimatePresence, motion } from 'motion-v'
import { FADE, RISE, rowDelay } from '@/motion'
import type { TableColumn } from '@/types'

/**
 * Tabel yang dipakai tiap layar daftar — dirakit dari component set Figma
 * `Cell` (`252:1743`): baris header berlatar CoolGray/10 dengan `Subtitle/S`,
 * baris isi `Body/S`, garis CoolGray/20, sel padding 16×12.
 *
 * `<table>` sungguhan, bukan grid div: baris header yang dikenali browser
 * berarti pembaca layar menyebut nama kolom saat kursor pindah sel — di layar
 * seperti News List (7 kolom) itu bedanya antara bisa dan tidak bisa dipakai.
 */
defineProps<{
    columns: TableColumn[]
    rows: T[]
    rowKey: keyof T & string
    emptyMessage?: string
}>()

/*
 * Slot per kolom diketik dengan `T`, bukan `Record<string, unknown>`.
 *
 * Sebelumnya halaman pemakainya harus menulis `(row as Row).title` di TIAP sel
 * — belasan cast per tabel, dan tiap cast itu titik di mana TypeScript berhenti
 * memeriksa. Dengan komponen generik, `row` di dalam slot sudah bertipe benar
 * dan salah ketik nama kolom ketahuan saat typecheck.
 */
defineSlots<{
    [K: `cell.${string}`]: (props: { row: T; value: unknown }) => unknown
}>()

/*
 * Satu-satunya tempat baris dibaca sebagai peta bebas.
 *
 * Konstrainnya `object`, bukan `Record<string, unknown>`: sebuah `interface`
 * tidak punya index signature implisit, jadi konstrain yang kedua menolak
 * hampir semua tipe baris yang ditulis wajar. Cast-nya dikurung di sini —
 * satu, di dalam komponen — alih-alih tersebar belasan kali di tiap halaman.
 */
function cell(row: T, key: string): unknown {
    return (row as Record<string, unknown>)[key]
}
</script>

<template>
    <motion.div
        class="w-full overflow-x-auto border border-cool-20 bg-surface"
        :initial="{ opacity: 0 }"
        :animate="{ opacity: 1 }"
        :transition="FADE"
    >
        <table class="w-full border-collapse">
            <thead>
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="border-t border-cool-20 bg-cool-10 px-3 py-4 text-left text-subtitle-s text-cool-100"
                        :class="column.align === 'right' ? 'text-right' : ''"
                        :style="column.width ? { width: column.width } : undefined"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>

            <tbody>
                <!--
                    `AnimatePresence` merender Fragment, bukan elemen — ia
                    memakai `TransitionGroup` Vue tanpa `tag`. Itu wajib di
                    sini: satu `<div>` liar di antara `<tbody>` dan `<tr>`
                    membuat HTML-nya tidak sah dan browser memindahkannya
                    keluar tabel.
                -->
                <AnimatePresence>
                    <motion.tr
                        v-if="rows.length === 0"
                        key="empty"
                        :initial="{ opacity: 0 }"
                        :animate="{ opacity: 1 }"
                        :exit="{ opacity: 0 }"
                        :transition="FADE"
                    >
                        <td
                            :colspan="columns.length"
                            class="border-t border-cool-20 px-3 py-8 text-center text-body-s text-cool-60"
                        >
                            {{ emptyMessage ?? 'Belum ada data.' }}
                        </td>
                    </motion.tr>

                    <!--
                        Baris masuk berurutan dari atas dan keluar ke KIRI.
                        Arah keluarnya sengaja berbeda dari arah masuknya:
                        setelah menekan Hapus, yang perlu terbaca adalah "baris
                        itu pergi", bukan "daftarnya menata ulang".

                        Jedanya dibatasi di `rowDelay()` — lihat alasannya di
                        `resources/js/motion.ts`.
                    -->
                    <motion.tr
                        v-for="(row, index) in rows"
                        :key="String(cell(row, rowKey))"
                        :initial="{ opacity: 0, y: 8 }"
                        :animate="{ opacity: 1, y: 0 }"
                        :exit="{ opacity: 0, x: -12, transition: FADE }"
                        :transition="{ ...RISE, delay: rowDelay(index) }"
                    >
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            class="border-t border-cool-20 px-3 py-4 text-body-s text-cool-100"
                            :class="column.align === 'right' ? 'text-right' : ''"
                        >
                            <slot :name="`cell.${column.key}`" :row="row" :value="cell(row, column.key)">
                                {{ cell(row, column.key) }}
                            </slot>
                        </td>
                    </motion.tr>
                </AnimatePresence>
            </tbody>
        </table>
    </motion.div>
</template>
