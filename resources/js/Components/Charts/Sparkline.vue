<script setup lang="ts">
import { computed } from 'vue'

/**
 * Garis 12 titik di dalam kartu statistik.
 *
 * Tanpa sumbu, tanpa kisi, tanpa tooltip — ia menunjukkan bentuk, bukan angka.
 * Angkanya sudah dicetak besar di sebelahnya, dan tabelnya ada di grafik utama.
 * `aria-hidden` karena tidak ada yang bisa dibacakan darinya.
 */
const props = defineProps<{ values: number[] }>()

const W = 120
const H = 32

const path = computed(() => {
    const values = props.values
    if (values.length < 2) return ''

    const max = Math.max(...values)
    const min = Math.min(...values)
    const span = max - min || 1

    return values
        .map((v, i) => {
            const x = (i / (values.length - 1)) * W
            // 2px disisakan di atas dan bawah supaya garis 2px tidak terpotong
            // separuh di tepi viewBox.
            const y = H - 2 - ((v - min) / span) * (H - 4)
            return `${x},${y}`
        })
        .join(' ')
})
</script>

<template>
    <svg :viewBox="`0 0 ${W} ${H}`" class="h-8 w-[120px]" aria-hidden="true" focusable="false">
        <polyline
            :points="path"
            fill="none"
            class="stroke-cool-40"
            stroke-width="2"
            stroke-linejoin="round"
            stroke-linecap="round"
        />
    </svg>
</template>
