<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhWarningCircle } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import StatTile from '@/Components/StatTile.vue'
import LineChart from '@/Components/Charts/LineChart.vue'
import ColumnChart from '@/Components/Charts/ColumnChart.vue'
import LandingSectionStatus from '@/Components/LandingSectionStatus.vue'
import type {
    ActivityEntry,
    ColumnPoint,
    LandingSection,
    SeriesMeta,
    SeriesPoint,
    SharedProps,
    StatTileData,
} from '@/types'

const props = defineProps<{
    range: string
    isEmpty: boolean
    stats: StatTileData[]
    publications: { points: SeriesPoint[]; series: SeriesMeta[] }
    inbound: ColumnPoint[]
    sections: LandingSection[]
    activity: ActivityEntry[]
}>()

const { t } = useI18n()

const page = usePage<SharedProps>()
const userName = computed(() => page.props.auth.user?.name ?? '')

// --- Filter rentang --------------------------------------------------------

const RANGES = computed(() => [
    { key: '30d', label: t('dashboard.range_30d') },
    { key: '90d', label: t('dashboard.range_90d') },
    { key: '12m', label: t('dashboard.range_12m') },
])

const reloading = ref(false)

function setRange(key: string): void {
    if (key === props.range) return

    // Muat ulang sebagian: hanya prop yang bergantung rentang. Sisa halaman —
    // section dan aktivitas — tidak ikut dihitung ulang.
    //
    // Tanpa `preserveScroll`/`preserveState`: `reload` sudah mempertahankan
    // keduanya, dan `ReloadOptions` memang membuang kedua kunci itu.
    router.reload({
        only: ['range', 'stats', 'publications'],
        data: { range: key },
        onStart: () => (reloading.value = true),
        onFinish: () => (reloading.value = false),
    })
}

// --- Waktu relatif ---------------------------------------------------------

const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | undefined

onMounted(() => {
    // Sekali per menit sudah cukup: yang ditampilkan "3 jam lalu", bukan detik.
    ticker = setInterval(() => (now.value = Date.now()), 60_000)
})
onBeforeUnmount(() => clearInterval(ticker))

function relative(iso: string): string {
    const diff = now.value - new Date(iso).getTime()
    const minutes = Math.round(diff / 60_000)

    if (minutes < 60) return t('activity.minutes_ago', { count: minutes })
    if (minutes < 1440) return t('activity.hours_ago', { count: Math.round(minutes / 60) })
    return t('activity.days_ago', { count: Math.round(minutes / 1440) })
}
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <AdminLayout>
        <PageHeader :title="t('dashboard.title')">
            <template #description>{{ t('dashboard.greeting', { name: userName }) }}</template>
        </PageHeader>

        <!-- Keadaan kosong yang jujur: tidak ada angka karangan, cuma
             keterangan bahwa memang belum ada isinya. -->
        <div
            v-if="isEmpty"
            class="flex items-start gap-3 border-l-4 border-primary-60 bg-surface px-4 py-3"
            role="note"
        >
            <PhWarningCircle :size="20" class="mt-0.5 shrink-0 text-cool-60" aria-hidden="true" />
            <p class="text-body-s text-cool-90">
<strong>{{ t('dashboard.empty_title') }}</strong>
                {{ t('dashboard.empty_body', { command: 'php artisan db:seed' }) }}
            </p>
        </div>

        <!-- Filter satu baris, di atas semua yang ia lingkupi. -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-body-xs text-cool-60">{{ t('dashboard.range') }}</span>
            <div class="flex" role="group" :aria-label="t('dashboard.range_group')">
                <button
                    v-for="option in RANGES"
                    :key="option.key"
                    type="button"
                    class="cursor-pointer border px-3 py-1.5 text-body-xs transition-colors -ml-px first:ml-0"
                    :class="
                        option.key === range
                            ? 'border-cool-90 bg-cool-90 text-on-inverse'
                            : 'border-cool-30 bg-surface text-cool-70 hover:border-cool-60'
                    "
                    :aria-pressed="option.key === range"
                    @click="setRange(option.key)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>

        <!-- Saat data dimuat ulang, grafik menahan render sebelumnya dengan
             opasitas berkurang — tanpa skeleton, tanpa layar melompat. -->
        <div
            class="flex flex-col gap-6 transition-opacity"
            :class="reloading ? 'opacity-50' : 'opacity-100'"
            :aria-busy="reloading"
        >
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-4">
                <StatTile v-for="stat in stats" :key="stat.key" :stat="stat" />
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <CardSection :title="t('dashboard.publications')">
                        <template #header>
                            <span class="text-body-xs text-cool-60">
{{ t('dashboard.publications_range', { range: RANGES.find((r) => r.key === range)?.label ?? '' }) }}
                            </span>
                        </template>

                        <LineChart
                            :points="publications.points"
                            :series="publications.series"
                            :caption="t('dashboard.publications_caption')"
                        />
                    </CardSection>
                </div>

                <CardSection :title="t('dashboard.sections')">
                    <LandingSectionStatus :sections="sections" />
                </CardSection>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <CardSection :title="t('dashboard.inbound')">
                <template #header>
                    <span class="text-body-xs text-cool-60">{{ t('dashboard.inbound_window') }}</span>
                </template>

                <ColumnChart
                    :points="inbound"
                    :caption="t('dashboard.inbound_caption')"
                />
            </CardSection>

            <CardSection :title="t('dashboard.activity')">
                <p v-if="activity.length === 0" class="py-6 text-center text-body-s text-cool-60">
                    {{ t('dashboard.activity_empty') }}
                </p>

                <ul v-else class="flex flex-col">
                    <li
                        v-for="entry in activity"
                        :key="entry.id"
                        class="border-t border-cool-20 first:border-t-0"
                    >
                        <Link
                            :href="entry.href"
                            class="flex flex-col gap-1 py-3 transition-colors hover:bg-cool-10"
                        >
                            <span class="text-body-s text-cool-90">
                                <strong class="font-medium">{{ entry.actor }}</strong>
                                {{ entry.action }}
                                <span class="text-cool-70">{{ entry.target }}</span>
                            </span>
                            <time :datetime="entry.at" class="text-body-xs text-cool-60">
                                {{ relative(entry.at) }}
                            </time>
                        </Link>
                    </li>
                </ul>
            </CardSection>
        </div>

    </AdminLayout>
</template>
