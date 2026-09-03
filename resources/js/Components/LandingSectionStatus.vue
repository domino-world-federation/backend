<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhCaretRight, PhCheckCircle, PhCircleDashed, PhClock, PhWarning } from '@phosphor-icons/vue'
import type { LandingSection, SectionStatus } from '@/types'

/**
 * Kelengkapan tiap section landing page — pertanyaan paling berguna untuk CMS
 * yang isinya satu halaman: mana yang sudah diisi, mana yang belum.
 *
 * Bukan grafik. Tujuh kategori berlabel dengan status masing-masing adalah
 * daftar, dan mengubahnya jadi donat justru menyembunyikan section mana yang
 * bermasalah.
 *
 * Warna status **selalu** datang bersama ikon dan teks. `warning` (`#FAB219`)
 * kontrasnya cuma 1.79:1 di atas putih — ia terbaca karena ada kata di
 * sebelahnya, bukan karena warnanya.
 */
const props = defineProps<{ sections: LandingSection[] }>()

const { t } = useI18n()

const STATUS: Record<SectionStatus, { icon: typeof PhCheckCircle; token: string }> = {
    ready: { icon: PhCheckCircle, token: 'var(--color-status-good)' },
    incomplete: { icon: PhWarning, token: 'var(--color-status-warning)' },
    empty: { icon: PhCircleDashed, token: 'var(--color-status-critical)' },
    // Belum bisa dinilai karena modulnya belum ada. Abu-abu, bukan hijau:
    // status "siap" palsu justru menghentikan orang memeriksa.
    unknown: { icon: PhClock, token: 'var(--color-cool-40)' },
}

const ready = computed(() => props.sections.filter((s) => s.status === 'ready').length)

/**
 * Penyebutnya hanya section yang benar-benar bisa dinilai.
 *
 * Kalau section `unknown` ikut dihitung, meter-nya melaporkan "14% siap" dan
 * angka itu tidak berarti apa-apa — ia mencampur "belum diisi" dengan "belum
 * ada cara mengeceknya".
 */
const assessable = computed(() => props.sections.filter((s) => s.status !== 'unknown'))
const percent = computed(() =>
    assessable.value.length === 0
        ? 0
        : Math.round((ready.value / assessable.value.length) * 100),
)
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Meter: isian membawa keadaan, lintasannya langkah yang lebih terang
             dari ramp yang sama — bukan abu-abu asing. -->
        <div class="flex flex-col gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <span class="text-body-s text-cool-70">
                    {{ t('dashboard.sections_progress', { ready, total: assessable.length }) }}
                </span>
                <span class="text-body-s font-medium text-cool-90 tabular-nums">
                    {{ percent }}%
                </span>
            </div>

            <div
                class="h-2 w-full overflow-hidden rounded-full bg-cool-20"
                role="progressbar"
                :aria-valuenow="percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('dashboard.sections_aria')"
            >
                <div
                    class="h-full rounded-full transition-[width] duration-300"
                    :style="{ width: `${percent}%`, background: 'var(--color-series-1)' }"
                />
            </div>
        </div>

        <ul class="flex flex-col">
            <li
                v-for="section in sections"
                :key="section.key"
                class="border-t border-cool-20 first:border-t-0"
            >
                <Link
                    :href="section.href"
                    class="flex items-center gap-3 py-3 transition-colors hover:bg-cool-10"
                >
                    <component
                        :is="STATUS[section.status].icon"
                        :size="20"
                        aria-hidden="true"
                        :style="{ color: STATUS[section.status].token }"
                        class="shrink-0"
                    />

                    <span class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-body-s text-cool-90">{{ section.label }}</span>
                        <span class="truncate text-body-xs text-cool-60">
                            {{ t(`section_status.${section.status}`) }} · {{ section.note }}
                        </span>
                    </span>

                    <PhCaretRight :size="16" class="shrink-0 text-cool-40" aria-hidden="true" />
                </Link>
            </li>
        </ul>
    </div>
</template>
