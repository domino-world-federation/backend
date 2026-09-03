<script setup lang="ts">
import { computed, ref } from 'vue'
import ChartTable from '@/Components/Charts/ChartTable.vue'
import type { ColumnPoint } from '@/types'

/**
 * Grafik kolom satu seri.
 *
 * Tanpa legenda — satu seri, jadi judul kartunya sudah menyebutkan apa yang
 * diplot; kotak legenda berisi satu contoh warna cuma mengulang judul.
 *
 * Tiap kolom adalah sasaran arahnya sendiri (tanpa crosshair), bisa difokus
 * keyboard, dan tooltipnya muncul baik saat disorot maupun saat difokus.
 */
const props = withDefaults(
    defineProps<{ points: ColumnPoint[]; caption: string; height?: number }>(),
    { height: 200 },
)

const PAD = { top: 16, right: 8, bottom: 28, left: 32 }
const WIDTH = 560
/** Kolom tidak pernah memenuhi slotnya — sisanya sengaja jadi udara. */
const MAX_BAR = 24

const active = ref<number | null>(null)

const plot = computed(() => ({
    w: WIDTH - PAD.left - PAD.right,
    h: props.height - PAD.top - PAD.bottom,
}))

const maxValue = computed(() => {
    const raw = Math.max(1, ...props.points.map((p) => p.value))
    const magnitude = 10 ** Math.floor(Math.log10(raw))
    return Math.ceil(raw / magnitude) * magnitude
})

const band = computed(() => plot.value.w / Math.max(1, props.points.length))
const barWidth = computed(() => Math.min(MAX_BAR, band.value - 6))

function xFor(index: number): number {
    return PAD.left + index * band.value + (band.value - barWidth.value) / 2
}

function heightFor(value: number): number {
    return (value / maxValue.value) * plot.value.h
}

const ticks = computed(() =>
    [0, maxValue.value / 2, maxValue.value].map((value) => ({
        value,
        y: PAD.top + plot.value.h - heightFor(value),
    })),
)

const tableRows = computed(() => props.points.map((p) => [p.label, p.value]))
</script>

<template>
    <figure class="m-0 flex flex-col gap-4">
        <div class="relative">
            <svg
                :viewBox="`0 0 ${WIDTH} ${height}`"
                class="w-full"
                :style="{ height: `${height}px` }"
                role="img"
                :aria-label="caption"
            >
                <g>
                    <line
                        v-for="tick in ticks"
                        :key="`g-${tick.value}`"
                        :x1="PAD.left"
                        :x2="WIDTH - PAD.right"
                        :y1="tick.y"
                        :y2="tick.y"
                        class="stroke-cool-20"
                        stroke-width="1"
                    />
                    <text
                        v-for="tick in ticks"
                        :key="`t-${tick.value}`"
                        :x="PAD.left - 8"
                        :y="tick.y + 4"
                        text-anchor="end"
                        class="fill-cool-60 text-[11px] tabular-nums"
                    >
                        {{ tick.value.toLocaleString('id-ID') }}
                    </text>
                </g>

                <g v-for="(point, index) in points" :key="point.iso">
                    <!-- Sasaran arah dibuat selebar slotnya, bukan selebar
                         kolomnya: kolom 4px pada hari yang sepi mustahil
                         dikenai pointer. -->
                    <rect
                        :x="PAD.left + index * band"
                        :y="PAD.top"
                        :width="band"
                        :height="plot.h"
                        fill="transparent"
                        tabindex="0"
                        role="button"
                        :aria-label="`${point.label}: ${point.value} pesan`"
                        class="cursor-default outline-offset-2"
                        @pointerenter="active = index"
                        @pointerleave="active = null"
                        @focus="active = index"
                        @blur="active = null"
                    />
                    <!-- Ujung data membulat 4px, pangkalnya siku di garis dasar:
                         dua rect bertumpuk lebih jujur daripada rx pada satu
                         rect, yang ikut membulatkan pangkalnya. -->
                    <rect
                        :x="xFor(index)"
                        :y="PAD.top + plot.h - heightFor(point.value)"
                        :width="barWidth"
                        :height="Math.max(heightFor(point.value), point.value > 0 ? 4 : 0)"
                        rx="4"
                        fill="var(--color-series-1)"
                        :opacity="active === null || active === index ? 1 : 0.45"
                        class="pointer-events-none transition-opacity"
                    />
                    <rect
                        v-if="point.value > 0"
                        :x="xFor(index)"
                        :y="PAD.top + plot.h - Math.min(4, heightFor(point.value))"
                        :width="barWidth"
                        :height="Math.min(4, heightFor(point.value))"
                        fill="var(--color-series-1)"
                        :opacity="active === null || active === index ? 1 : 0.45"
                        class="pointer-events-none transition-opacity"
                    />
                </g>

                <text
                    v-for="(point, index) in points"
                    :key="`x-${point.iso}`"
                    :x="PAD.left + index * band + band / 2"
                    :y="height - 8"
                    text-anchor="middle"
                    class="fill-cool-60 text-[11px]"
                >
                    {{ index % 2 === 0 ? point.label : '' }}
                </text>
            </svg>

            <div
                v-if="active !== null"
                class="pointer-events-none absolute top-0 border border-cool-20 bg-surface px-3 py-2 shadow-editor"
                :style="{
                    left: `${((PAD.left + active * band + band / 2) / WIDTH) * 100}%`,
                    transform: active > points.length / 2 ? 'translateX(-108%)' : 'translateX(8%)',
                }"
            >
                <p class="text-body-xs text-cool-60">{{ points[active]?.label }}</p>
                <p class="text-body-s font-medium text-cool-90 tabular-nums">
                    {{ points[active]?.value }} pesan
                </p>
            </div>
        </div>

        <figcaption>
            <ChartTable :caption="caption" :headers="['Tanggal', 'Pesan']" :rows="tableRows" />
        </figcaption>
    </figure>
</template>
