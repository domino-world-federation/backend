<script setup lang="ts">
import { PhArrowDown, PhArrowUp, PhPlus, PhTrash } from '@phosphor-icons/vue'
import AppButton from '@/Components/AppButton.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Kelompok baris berulang: nomor, tombol hapus, opsional tombol urut, lalu
 * field-nya lewat slot.
 *
 * Bentuk ini muncul di enam layar — ofisial dan jadwal turnamen, pemenang,
 * hasil Olympic, statistik federasi, dan dua daftar komite. Disalin enam kali,
 * jaraknya akan menyimpang perlahan dan yang terasa bukan "yang satu salah"
 * melainkan "aplikasinya tidak rapi".
 *
 * Ia TIDAK memiliki datanya. `v-model` tetap di halaman pemakainya, karena
 * tiap daftar punya bentuk barisnya sendiri dan komponen ini tidak perlu tahu
 * apa pun tentangnya.
 */
withDefaults(
    defineProps<{
        /** Jumlah baris — dipakai untuk menonaktifkan tombol urut di ujung. */
        count: number
        emptyMessage: string
        addLabel: string
        /** Menampilkan panah naik/turun. Hanya untuk daftar yang urutannya diatur orang. */
        reorderable?: boolean
    }>(),
    { reorderable: false },
)

const emit = defineEmits<{
    (e: 'add'): void
    (e: 'remove', index: number): void
    (e: 'move', index: number, delta: number): void
}>()

const { t } = useI18n()
</script>

<template>
    <p v-if="count === 0" class="text-body-s text-cool-60">{{ emptyMessage }}</p>

    <div
        v-for="index in count"
        :key="index - 1"
        class="flex w-full flex-col gap-3 border-b border-cool-20 pb-4 last:border-b-0"
    >
        <div class="flex items-center justify-between">
            <span class="text-subtitle-s text-cool-90">#{{ index }}</span>

            <div class="flex items-center gap-3">
                <slot name="rowActions" :index="index - 1" />

                <template v-if="reorderable">
                    <button
                        type="button"
                        class="cursor-pointer text-cool-60 disabled:cursor-not-allowed disabled:text-cool-30"
                        :disabled="index === 1"
                        :aria-label="t('tournaments.move_up')"
                        @click="emit('move', index - 1, -1)"
                    >
                        <PhArrowUp :size="18" />
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer text-cool-60 disabled:cursor-not-allowed disabled:text-cool-30"
                        :disabled="index === count"
                        :aria-label="t('tournaments.move_down')"
                        @click="emit('move', index - 1, 1)"
                    >
                        <PhArrowDown :size="18" />
                    </button>
                </template>

                <button
                    type="button"
                    class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                    @click="emit('remove', index - 1)"
                >
                    <PhTrash :size="16" aria-hidden="true" />
                    {{ t('common.delete') }}
                </button>
            </div>
        </div>

        <slot :index="index - 1" />
    </div>

    <AppButton variant="outline" size="s" @click="emit('add')">
        <template #iconLeft><PhPlus :size="20" /></template>
        {{ addLabel }}
    </AppButton>
</template>
