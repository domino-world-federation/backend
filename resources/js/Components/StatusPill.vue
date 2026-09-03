<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Label status di kolom Visibility (`252:4398`).
 *
 * Warnanya selalu berpasangan dengan katanya — tidak ada keadaan yang
 * dibedakan hanya oleh warna.
 */
const props = defineProps<{ value: string }>()

const { t } = useI18n()

/** Nilainya kunci status ('posted'|'scheduled'|'draft'), bukan teks jadi. */
const label = computed(() => t(`news.status_${props.value}`))

const tone = computed(() => {
    const key = props.value
    if (key === 'posted') return 'border-transparent bg-cool-10 text-cool-90'
    if (key === 'scheduled') return 'border-primary-60 bg-transparent text-cool-90'
    if (key === 'unpublished') return 'border-cool-60 bg-transparent text-cool-70'
    return 'border-cool-30 bg-transparent text-cool-60'
})
</script>

<template>
    <span class="inline-flex border px-2 py-1 text-body-xs" :class="tone">{{ label }}</span>
</template>
