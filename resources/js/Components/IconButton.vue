<script setup lang="ts">
/**
 * Tombol ikon bulat 40px — Figma `433:6785` (`Style=Outline, Size=S`,
 * border 2px CoolGray/30, `border-radius: 76px`).
 *
 * Keadaan nonaktif digambar sebagai lingkaran ABU TERISI tanpa border, bukan
 * ikon yang diredupkan: di baris tabel, ikon pudar terbaca seperti gambar yang
 * gagal dimuat. Lingkaran penuh terbaca sebagai "tombol ini memang mati".
 *
 * Gerak sentuh dan tekannya memakai pegas, bukan transisi CSS: tombol ini yang
 * dipakai untuk Sunting dan Hapus di tiap baris, jadi ia harus terasa merespons
 * seketika. Pegas mulai cepat lalu mengendap sendiri; tween berdurasi tetap
 * selalu terasa terlambat sedikit di gerak sependek ini.
 *
 * Isyarat itu DIMATIKAN saat nonaktif. Tombol yang tetap membesar saat disentuh
 * menjanjikan sesuatu akan terjadi, lalu tidak terjadi apa-apa.
 */
import { motion } from 'motion-v'
import { SPRING_SNAP } from '@/motion'

withDefaults(
    defineProps<{
        label: string
        tone?: 'default' | 'danger' | 'success'
        disabled?: boolean
        /** Dibacakan pembaca layar dan muncul sebagai tooltip — dipakai untuk
         *  menjelaskan KENAPA sebuah tombol mati. */
        title?: string
    }>(),
    { tone: 'default', disabled: false },
)

const TONES = {
    default: 'text-cool-90',
    danger: 'text-danger',
    success: 'text-status-good',
}
</script>

<template>
    <motion.button
        type="button"
        class="flex size-10 shrink-0 items-center justify-center rounded-full transition-colors"
        :class="
            disabled
                ? 'cursor-not-allowed border-2 border-transparent bg-cool-20 text-cool-40'
                : ['border-2 border-cool-30 bg-surface hover:border-cool-60', TONES[tone]]
        "
        :disabled="disabled"
        :aria-label="label"
        :title="title ?? label"
        :while-hover="disabled ? undefined : { scale: 1.08 }"
        :while-press="disabled ? undefined : { scale: 0.9 }"
        :transition="SPRING_SNAP"
    >
        <slot />
    </motion.button>
</template>
