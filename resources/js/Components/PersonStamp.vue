<script setup lang="ts">
import { useI18n } from '@/composables/useI18n'
import { formatDateTime } from '@/utils/format'

/**
 * Sel "siapa + kapan" — pola yang berulang di tiga kolom daftar berita
 * (Published, Created, Last Modified) pada Figma `252:1743`: nama di atas,
 * waktunya kecil di bawahnya.
 *
 * Kalau keduanya kosong, yang dicetak satu tanda hubung, bukan dua baris kosong
 * yang membuat tinggi barisnya berbeda dari tetangganya.
 */
defineProps<{
    name?: string | null
    at?: string | null
}>()

const { t } = useI18n()
</script>

<template>
    <span v-if="!name && !at" class="text-body-s text-cool-60">{{ t('common.none') }}</span>

    <span v-else class="flex flex-col">
        <span v-if="name" class="text-body-s text-cool-90">{{ name }}</span>
        <span v-if="at" class="text-body-xs whitespace-nowrap text-cool-60">
            {{ formatDateTime(at) }}
        </span>
    </span>
</template>
