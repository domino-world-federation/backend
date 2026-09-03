<script setup lang="ts">
import { useI18n } from '@/composables/useI18n'
/**
 * Tampilan tabel untuk sebuah grafik.
 *
 * Wajib ada, bukan pelengkap: tooltip boleh memperkaya, tapi tidak boleh jadi
 * satu-satunya jalan menuju sebuah angka. Pembaca layar, pengguna keyboard, dan
 * siapa pun yang mau menyalin angkanya lewat sini.
 */
defineProps<{
    caption: string
    headers: string[]
    rows: Array<Array<string | number>>
}>()

const { t } = useI18n()
</script>

<template>
    <details class="group">
        <summary
            class="cursor-pointer list-none text-body-xs text-cool-60 underline underline-offset-2 hover:text-cool-90"
        >
            <span class="group-open:hidden">{{ t('dashboard.view_as_table') }}</span>
            <span class="hidden group-open:inline">{{ t('dashboard.hide_table') }}</span>
        </summary>

        <div class="mt-3 overflow-x-auto">
            <table class="w-full border-collapse border border-cool-20">
                <caption class="sr-only">{{ caption }}</caption>
                <thead>
                    <tr>
                        <th
                            v-for="header in headers"
                            :key="header"
                            scope="col"
                            class="border-b border-cool-20 bg-cool-10 px-3 py-2 text-left text-body-xs text-cool-90"
                        >
                            {{ header }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="index">
                        <td
                            v-for="(cell, cellIndex) in row"
                            :key="cellIndex"
                            class="border-b border-cool-20 px-3 py-2 text-body-xs text-cool-70"
                            :class="cellIndex > 0 ? 'tabular-nums' : ''"
                        >
                            {{ cell }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </details>
</template>
