<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { PhDownloadSimple } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    type: string
    excerpt: string
    isRead: boolean
    receivedAt: string | null
}

const props = defineProps<{
    reports: Paginated<Row>
    types: string[]
    unreadCount: number
    filters: { q: string; type: string; status: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/integrity-reports', {
    q: props.filters.q,
    type: props.filters.type,
    status: props.filters.status,
})

function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/integrity-reports/export' : `/integrity-reports/export?${query}`
}

/**
 * Tidak ada kolom pengirim — tidak ada yang bisa dicetak di sana.
 *
 * Saluran ini tidak menyimpan nama, email, maupun alamat IP. Kolom kosong
 * bernama "Pengirim" akan terbaca seperti data yang hilang, bukan seperti data
 * yang memang tidak pernah diminta.
 */
const columns: TableColumn[] = [
    { key: 'report', label: t('integrity.report') },
    { key: 'type', label: t('integrity.incident_type'), width: '240px' },
    { key: 'receivedAt', label: t('integrity.received'), width: '200px' },
]
</script>

<template>
    <Head :title="t('integrity.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('integrity.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('integrity.title') }]"
        >
            <template #description>
                {{ t('integrity.hint') }} {{ t('integrity.count', { count: unreadCount }) }}
            </template>
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
            </template>
        </PageHeader>

        <ContextNote tone="security">{{ t('integrity.anonymous_note') }}</ContextNote>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('integrity.search')"
            :filters="[
                {
                    key: 'status',
                    label: t('common.status'),
                    value: state.status,
                    options: [
                        { value: 'unread', label: t('messages.unread') },
                        { value: 'read', label: t('messages.read') },
                    ],
                },
                {
                    key: 'type',
                    label: t('integrity.incident_type'),
                    value: state.type,
                    options: types.map((item) => ({ value: item, label: item })),
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="reports.data"
                row-key="id"
                :empty-message="t('integrity.empty')"
            >
                <template #cell.report="{ row }">
                    <Link :href="`/integrity-reports/${row.id}`" class="flex items-center gap-2">
                        <span
                            v-if="!row.isRead"
                            class="size-2 shrink-0 rounded-full bg-primary-60"
                            aria-hidden="true"
                        />
                        <span
                            class="text-body-s text-cool-100 underline underline-offset-2"
                            :class="row.isRead ? '' : 'font-medium'"
                        >
                            {{ row.excerpt }}
                        </span>
                        <span v-if="!row.isRead" class="sr-only">{{ t('messages.unread_badge') }}</span>
                    </Link>
                </template>

                <template #cell.type="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.type }}</span>
                </template>

                <template #cell.receivedAt="{ row }">
                    <span class="text-body-s text-cool-70">{{ formatDateTime(row.receivedAt) }}</span>
                </template>
            </DataTable>

            <AppPagination
                :current-page="reports.current_page"
                :last-page="reports.last_page"
                :href-for="(n) => `/integrity-reports?page=${n}`"
                @navigate="go($event)"
            />
        </div>
    </AdminLayout>
</template>
