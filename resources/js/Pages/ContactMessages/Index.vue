<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { PhDownloadSimple } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    email: string
    topic: string | null
    subject: string
    isRead: boolean
    receivedAt: string | null
}

const props = defineProps<{
    messages: Paginated<Row>
    topics: string[]
    unreadCount: number
    filters: { q: string; topic: string; status: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/contact-messages', {
    q: props.filters.q,
    topic: props.filters.topic,
    status: props.filters.status,
})

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di News. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/contact-messages/export' : `/contact-messages/export?${query}`
}

const columns: TableColumn[] = [
    { key: 'subject', label: t('messages.subject') },
    { key: 'sender', label: t('messages.sender') },
    { key: 'topic', label: t('messages.topic') },
    { key: 'receivedAt', label: t('messages.received') },
]
</script>

<template>
    <Head :title="t('messages.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('messages.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('messages.title') }]"
        >
            <template #description>
{{ t('messages.unread_summary', { count: unreadCount }) }}
            </template>
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('messages.search_placeholder')"
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
                    key: 'topic',
                    label: t('messages.topic'),
                    value: state.topic,
                    options: topics.map((t) => ({ value: t, label: t })),
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="messages.data"
                row-key="id"
                :empty-message="t('messages.empty')"
            >
                <template #cell.subject="{ row }">
                    <Link :href="`/contact-messages/${row.id}`" class="flex items-center gap-2">
                        <!-- Belum dibaca ditandai titik DAN tebal, tidak hanya
                             warna — dan ada teks tersembunyi untuk pembaca layar. -->
                        <span
                            v-if="!row.isRead"
                            class="size-2 shrink-0 rounded-full bg-primary-60"
                            aria-hidden="true"
                        />
                        <span
                            class="text-body-s text-cool-100 underline underline-offset-2"
                            :class="row.isRead ? '' : 'font-medium'"
                        >
                            {{ row.subject }}
                        </span>
                        <span v-if="!row.isRead" class="sr-only">{{ t('messages.unread_badge') }}</span>
                    </Link>
                </template>

                <template #cell.sender="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ row.name }}</span>
                        <span class="text-body-xs text-cool-60">{{ row.email }}</span>
                    </span>
                </template>

                <template #cell.topic="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.topic ?? t('common.none') }}</span>
                </template>

                <template #cell.receivedAt="{ row }">
                    <span class="text-body-s text-cool-70">{{ formatDateTime(row.receivedAt) }}</span>
                </template>
            </DataTable>

            <AppPagination
                :current-page="messages.current_page"
                :last-page="messages.last_page"
                :href-for="(n) => `/contact-messages?page=${n}`"
                @navigate="go($event)"
            />
        </div>
    </AdminLayout>
</template>
