<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { PhArrowSquareOut, PhPencilSimple } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import DataTable from '@/Components/DataTable.vue'
import AppButton from '@/Components/AppButton.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useI18n } from '@/composables/useI18n'
import type { TableColumn } from '@/types'

/**
 * Daftar halaman yang naskahnya bisa disunting di editor halaman.
 *
 * Bukan layar daftar "standar" (tanpa filter, ekspor, atau sakelar keadaan):
 * isinya bukan rekaman yang bertambah, melainkan halaman situs yang jumlahnya
 * ditentukan kode — `config('dwf.pages')`. Yang berguna di sini hanya dua
 * pertanyaan: halaman mana yang punya perubahan belum terbit, dan siapa yang
 * terakhir menyentuhnya.
 */
interface PageRow {
    key: string
    label: string
    path: string
    fields: number
    drafts: number
    updatedAt: string | null
    updatedBy: string | null
}

defineProps<{ pages: PageRow[] }>()

const { t } = useI18n()

const columns: TableColumn[] = [
    { key: 'page', label: t('pages.column_page') },
    { key: 'fields', label: t('pages.column_fields'), width: '120px' },
    { key: 'drafts', label: t('pages.column_drafts'), width: '220px' },
    { key: 'updated', label: t('pages.column_updated'), width: '220px' },
    { key: 'actions', label: '', align: 'right', width: '140px' },
]
</script>

<template>
    <Head :title="t('pages.title')" />

    <AdminLayout>
        <PageHeader :title="t('pages.title')" :breadcrumbs="[{ label: t('pages.title') }]">
            <template #description>{{ t('pages.hint') }}</template>
        </PageHeader>

        <DataTable :columns="columns" :rows="pages" row-key="key">
            <template #cell.page="{ row }">
                <Link :href="`/pages/${row.key}`" class="flex flex-col">
                    <span class="text-body-s font-medium text-cool-100 hover:underline">{{ row.label }}</span>
                    <span class="text-body-xs font-mono text-cool-60">{{ row.path }}</span>
                </Link>
            </template>

            <template #cell.fields="{ row }">
                <span class="text-body-s text-cool-90">{{ row.fields }}</span>
            </template>

            <template #cell.drafts="{ row }">
                <span
                    v-if="row.drafts > 0"
                    class="inline-flex items-center gap-2 text-body-s font-medium text-cool-90"
                >
                    <span class="size-2 rounded-full bg-status-warning" />
                    {{ t('pages.drafts_count', { count: row.drafts }) }}
                </span>
                <span v-else class="text-body-s text-cool-60">{{ t('pages.no_drafts') }}</span>
            </template>

            <template #cell.updated="{ row }">
                <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
            </template>

            <template #cell.actions="{ row }">
                <div class="flex items-center justify-end gap-2">
                    <AppButton :href="`/pages/${row.key}`" variant="outline" size="s">
                        <template #iconLeft><PhPencilSimple :size="16" /></template>
                        {{ t('pages.edit') }}
                    </AppButton>
                </div>
            </template>
        </DataTable>

        <p class="mt-4 flex items-center gap-1 text-body-xs text-cool-60">
            <PhArrowSquareOut :size="14" />
            {{ t('pages.click_hint') }}
        </p>
    </AdminLayout>
</template>
