<script setup lang="ts">
import { computed, ref } from 'vue'
import ChartTable from '@/Components/Charts/ChartTable.vue'
import type { SeriesPoint, SeriesMeta } from '@/types'

/**
 * Grafik garis multi-seri. SVG tulis tangan — tanpa pustaka grafik.
 *
 * Alasannya bukan penghematan byte semata: warna, tebal garis, dan warna
 * chrome-nya harus tunduk pada token desain dan ikut berubah di mode gelap.
 * Membungkus pustaka lalu menimpa gayanya lebih banyak kode daripada menggambar
 * `<polyline>` sendiri.
 *
 * Aturan yang dipatuhi (lihat catatan di `resources/css/app.css`):
 * - satu sumbu Y saja — dua skala di satu bidang mengarang korelasi;
 * - garis 2px, penanda ujung r=4 dengan cincin 2px sewarna permukaan;
 * - kisi hairline 1px solid, resesif — tidak pernah putus-putus;
 * - legenda selalu ada untuk >= 2 seri, ditambah label langsung di ujung;
 * - teks tidak pernah memakai warna seri; identitas dibawa penanda di sebelahnya.
 */
const props = withDefaults(
    defineProps<{
        points: SeriesPoint[]
        series: SeriesMeta[]
        /** Judul untuk caption tabel; grafiknya sendiri diberi judul oleh kartu. */
        caption: string
        height?: number
    }>(),
    { height: 240 },
)

const PAD = { top: 16, right: 56, bottom: 28, left: 36 }
const WIDTH = 720

const hovered = ref<number | null>(null)
const svg = ref<SVGSVGElement | null>(null)

const plot = computed(() => ({
    w: WIDTH - PAD.left - PAD.right,
    h: props.height - PAD.top - PAD.bottom,
}))

/** Batas atas dibulatkan ke angka bersih supaya tick sumbu terbaca. */
const maxValue = computed(() => {
    const raw = Math.max(1, ...props.points.flatMap((p) => p.values))
    const magnitude = 10 ** Math.floor(Math.log10(raw))
    return Math.ceil(raw / magnitude) * magnitude
})

const ticks = computed(() => {
    const max = maxValue.value
    return [0, max / 2, max].map((value) => ({ value, y: yFor(value) }))
})

function xFor(index: number): number {
    if (props.points.length <= 1) return PAD.left
    return PAD.left + (index / (props.points.length - 1)) * plot.value.w
}

function yFor(value: number): number {
    return PAD.top + plot.value.h - (value / maxValue.value) * plot.value.h
}

const paths = computed(() =>
    props.series.map((_, s) =>
        props.points.map((p, i) => `${xFor(i)},${yFor(p.values[s] ?? 0)}`).join(' '),
    ),
)

/** Label sumbu X dijarangkan supaya tidak saling tabrakan di rentang panjang. */
const xLabels = computed(() => {
    const every = Math.max(1, Math.ceil(props.points.length / 8))
    return props.points
        .map((p, i) => ({ ...p, i }))
        .filter((p) => p.i % every === 0 || p.i === props.points.length - 1)
})

function onMove(event: PointerEvent): void {
    const rect = svg.value?.getBoundingClientRect()
    if (!rect || props.points.length === 0) return

    // Koordinat pointer diubah ke ruang viewBox dulu; SVG-nya diskalakan
    // responsif, jadi piksel layar bukan piksel grafik.
    const x = ((event.clientX - rect.left) / rect.width) * WIDTH
    const ratio = (x - PAD.left) / plot.value.w
    const index = Math.round(ratio * (props.points.length - 1))

    hovered.value = Math.min(props.points.length - 1, Math.max(0, index))
}

const tableRows = computed(() =>
    props.points.map((p) => [p.label, ...p.values.map((v) => v.toLocaleString('id-ID'))]),
)
</script>

<template>
    <figure class="m-0 flex flex-col gap-4">
        <!-- Legenda selalu ada untuk dua seri atau lebih: identitas tidak boleh
             hanya bergantung pada pencocokan warna. -->
        <ul class="flex flex-wrap items-center gap-4">
            <li v-for="(item, index) in series" :key="item.key" class="flex items-center gap-2">
                <span
                    class="block h-0.5 w-4 rounded-full"
                    :style="{ background: `var(--color-series-${index + 1})` }"
                    aria-hidden="true"
                />
                <span class="text-body-xs text-cool-70">{{ item.label }}</span>
            </li>
        </ul>

        <div class="relative">
            <svg
                ref="svg"
                :viewBox="`0 0 ${WIDTH} ${height}`"
                class="w-full"
                :style="{ height: `${height}px` }"
                role="img"
                :aria-label="caption"
                @pointermove="onMove"
                @pointerleave="hovered = null"
            >
                <!-- Kisi: hairline solid, satu langkah dari permukaan. -->
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

                <text
                    v-for="label in xLabels"
                    :key="`x-${label.iso}`"
                    :x="xFor(label.i)"
                    :y="height - 8"
                    text-anchor="middle"
                    class="fill-cool-60 text-[11px]"
                >
                    {{ label.label }}
                </text>

                <!-- Crosshair menemukan X; pembaca membidik tanggal, bukan garis 2px. -->
                <line
                    v-if="hovered !== null"
                    :x1="xFor(hovered)"
                    :x2="xFor(hovered)"
                    :y1="PAD.top"
                    :y2="PAD.top + plot.h"
                    class="stroke-cool-40"
                    stroke-width="1"
                />

                <polyline
                    v-for="(path, index) in paths"
                    :key="`p-${index}`"
                    :points="path"
                    fill="none"
                    :stroke="`var(--color-series-${index + 1})`"
                    stroke-width="2"
                    stroke-linejoin="round"
                    stroke-linecap="round"
                />

                <!-- Penanda ujung + label langsung. Hanya di ujung: nilai di
                     setiap titik jadi kekacauan dan tidak terbaca siapa pun. -->
                <template v-for="(item, index) in series" :key="`e-${item.key}`">
                    <circle
                        :cx="xFor(points.length - 1)"
                        :cy="yFor(points[points.length - 1]?.values[index] ?? 0)"
                        r="4"
                        :fill="`var(--color-series-${index + 1})`"
                        class="stroke-surface"
                        stroke-width="2"
                    />
                    <text
                        :x="xFor(points.length - 1) + 10"
                        :y="yFor(points[points.length - 1]?.values[index] ?? 0) + 4"
                        class="fill-cool-90 text-[11px] font-medium tabular-nums"
                    >
                        {{ points[points.length - 1]?.values[index] ?? 0 }}
                    </text>
                </template>

                <!-- Titik yang sedang disorot, di atas garis, bercincin permukaan. -->
                <template v-if="hovered !== null">
                    <circle
                        v-for="(item, index) in series"
                        :key="`h-${item.key}`"
                        :cx="xFor(hovered)"
                        :cy="yFor(points[hovered]?.values[index] ?? 0)"
                        r="4"
                        :fill="`var(--color-series-${index + 1})`"
                        class="stroke-surface"
                        stroke-width="2"
                    />
                </template>
            </svg>

            <!-- Satu tooltip, seluruh seri: pointer tidak perlu mendarat di
                 garis mana pun untuk mendapat angkanya. -->
            <div
                v-if="hovered !== null"
                class="pointer-events-none absolute top-2 border border-cool-20 bg-surface px-3 py-2 shadow-editor"
                :style="{
                    left: `${(xFor(hovered) / WIDTH) * 100}%`,
                    transform:
                        hovered > points.length / 2 ? 'translateX(-108%)' : 'translateX(8%)',
                }"
            >
                <p class="text-body-xs text-cool-60">{{ points[hovered]?.label }}</p>
                <ul class="mt-1 flex flex-col gap-0.5">
                    <li
                        v-for="(item, index) in series"
                        :key="`tt-${item.key}`"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="block h-0.5 w-3 rounded-full"
                            :style="{ background: `var(--color-series-${index + 1})` }"
                            aria-hidden="true"
                        />
                        <!-- Nilai memimpin, nama seri mengikuti. -->
                        <span class="text-body-s font-medium text-cool-90 tabular-nums">
                            {{ points[hovered]?.values[index] ?? 0 }}
                        </span>
                        <span class="text-body-xs text-cool-60">{{ item.label }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <figcaption>
            <ChartTable
                :caption="caption"
                :headers="['Periode', ...series.map((s) => s.label)]"
                :rows="tableRows"
            />
        </figcaption>
    </figure>
</template>
