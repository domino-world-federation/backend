<script setup lang="ts">
import { useI18n } from '@/composables/useI18n'
import { formatDateTime } from '@/utils/format'

/**
 * Blok "keterangan baris" di layar baca — kisi label kecil di atas nilainya.
 *
 * Satu komponen karena tiap layar detail memerlukannya dan isinya berbeda-beda:
 * kalau tiap layar menulis kisinya sendiri, jumlah kolom dan jarak antarbarisnya
 * akan menyimpang perlahan, dan yang terasa bukan "yang satu salah" melainkan
 * "aplikasinya tidak rapi".
 *
 * `value` boleh kosong, `at` boleh kosong, dan boleh keduanya ada — baris
 * "Last Modified" memang menyebut nama DAN waktu.
 */
defineProps<{
    items: Array<{ label: string; value?: string | null; at?: string | null }>
}>()

const { t } = useI18n()
</script>

<template>
    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in items" :key="item.label" class="flex flex-col gap-1">
            <dt class="text-body-xs text-cool-60">{{ item.label }}</dt>
            <dd class="text-body-s text-cool-90">
                <template v-if="item.value">{{ item.value }}</template>
                <template v-else-if="!item.at">{{ t('common.none') }}</template>

                <span v-if="item.at" class="block text-body-xs text-cool-60">
                    {{ formatDateTime(item.at) }}
                </span>
            </dd>
        </div>
    </dl>
</template>
