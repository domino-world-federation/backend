<script setup lang="ts">
import { computed } from 'vue'
import { PhArrowDown, PhArrowUp, PhMinus } from '@phosphor-icons/vue'
import Sparkline from '@/Components/Charts/Sparkline.vue'
import type { StatTileData } from '@/types'
import { useI18n } from '@/composables/useI18n'

/**
 * Kartu statistik: label · nilai · selisih · sparkline.
 *
 * Satu angka terkini bukan grafik batang satu batang — ia angka, dicetak besar.
 *
 * Warna selisih ditentukan **arah × apakah naik itu bagus**. "Draf" dan "Pesan
 * belum dibaca" naik = memburuk, jadi hijau di sana akan berbohong. Panahnya
 * selalu ikut, supaya arahnya tidak hanya dibawa warna.
 */
const props = defineProps<{ stat: StatTileData }>()

const { t } = useI18n()

const direction = computed(() => (props.stat.delta === 0 ? 'flat' : props.stat.delta > 0 ? 'up' : 'down'))

const isGood = computed(() =>
    direction.value === 'flat'
        ? null
        : (direction.value === 'up') === props.stat.upIsGood,
)

const icon = computed(() =>
    direction.value === 'flat' ? PhMinus : direction.value === 'up' ? PhArrowUp : PhArrowDown,
)
</script>

<template>
    <div class="flex flex-col gap-4 bg-surface p-6">
        <p class="text-subtitle-s text-cool-60">{{ t(`dashboard.stat_${stat.key}`) }}</p>

        <div class="flex items-end justify-between gap-4">
            <div class="flex flex-col gap-2">
                <p class="text-heading-4 text-cool-90 tabular-nums">
                    {{ stat.value.toLocaleString('id-ID') }}
                </p>

                <p class="flex items-center gap-1.5 text-body-xs">
                    <component
                        :is="icon"
                        :size="14"
                        weight="bold"
                        aria-hidden="true"
                        :style="
                            isGood === null
                                ? undefined
                                : {
                                      color: isGood
                                          ? 'var(--color-status-good)'
                                          : 'var(--color-status-critical)',
                                  }
                        "
                        :class="isGood === null ? 'text-cool-60' : ''"
                    />
                    <span class="tabular-nums text-cool-90">
                        {{ stat.delta > 0 ? '+' : '' }}{{ stat.delta }}%
                    </span>
                    <span class="text-cool-60">{{ t('dashboard.delta_label') }}</span>
                </p>
            </div>

            <Sparkline :values="stat.spark" />
        </div>
    </div>
</template>
