<script setup lang="ts">
import { PhCaretDown, PhMagnifyingGlass } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Baris filter di atas tabel (`252:2371`): beberapa dropdown di kiri, pencarian
 * di kanan.
 *
 * Satu baris, di atas isi yang ia lingkupi — tidak pernah di dalam kartu tabel,
 * tidak pernah satu filter per kolom.
 */
defineProps<{
    filters: Array<{
        key: string
        label: string
        value: string
        options: Array<{ value: string; label: string }>
    }>
    searchPlaceholder?: string
}>()

const { t } = useI18n()

const emit = defineEmits<{
    (e: 'change', key: string, value: string): void
    (e: 'search', value: string): void
}>()

const search = defineModel<string>('search', { default: '' })
</script>

<template>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <div v-for="filter in filters" :key="filter.key" class="relative">
                <label :for="`filter-${filter.key}`" class="sr-only">{{ filter.label }}</label>
                <select
                    :id="`filter-${filter.key}`"
                    :value="filter.value"
                    class="h-10 cursor-pointer appearance-none bg-surface py-2 pr-9 pl-3 text-body-s text-cool-100"
                    @change="emit('change', filter.key, ($event.target as HTMLSelectElement).value)"
                >
                    <option value="">{{ filter.label }}</option>
                    <option v-for="option in filter.options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <PhCaretDown
                    :size="20"
                    class="pointer-events-none absolute top-1/2 right-2 -translate-y-1/2 text-cool-100"
                    aria-hidden="true"
                />
            </div>
        </div>

        <div class="relative">
            <label for="filter-search" class="sr-only">{{ t('common.search') }}</label>
            <PhMagnifyingGlass
                :size="24"
                class="pointer-events-none absolute top-1/2 left-4 -translate-y-1/2 text-cool-60"
                aria-hidden="true"
            />
            <input
                id="filter-search"
                v-model="search"
                type="search"
                :placeholder="searchPlaceholder ?? t('common.search')"
                class="h-12 w-[210px] border-b border-cool-30 bg-cool-10 pr-4 pl-12 text-body-m text-cool-90 placeholder:text-cool-60"
                @keyup.enter="emit('search', search)"
                @search="emit('search', search)"
            />
        </div>
    </div>
</template>
