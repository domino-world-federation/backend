<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhDownloadSimple } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppToggle from '@/Components/AppToggle.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import RowMenu from '@/Components/RowMenu.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    email: string
    isSubscribed: boolean
    joinedAt: string | null
    leftAt: string | null
}

const props = defineProps<{
    subscribers: Paginated<Row>
    subscribedCount: number
    filters: { q: string; status: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/newsletter', {
    q: props.filters.q,
    status: props.filters.status,
})

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di modul lain. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/newsletter/export' : `/newsletter/export?${query}`
}

const columns: TableColumn[] = [
    { key: 'email', label: t('newsletter.email') },
    { key: 'joined', label: t('newsletter.joined'), width: '200px' },
    { key: 'subscribed', label: t('newsletter.subscribed'), width: '160px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

function toggle(row: Row, value: boolean): void {
    router.patch(`/newsletter/${row.id}/status`, { is_subscribed: value }, { preserveScroll: true })
}

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/newsletter/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('newsletter.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('newsletter.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('newsletter.title') }]"
        >
            <template #description>
                {{ t('newsletter.hint') }} {{ t('newsletter.count', { count: subscribedCount }) }}
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
            :search-placeholder="t('newsletter.search')"
            :filters="[
                {
                    key: 'status',
                    label: t('common.status'),
                    value: state.status,
                    options: [
                        { value: 'subscribed', label: t('newsletter.subscribed') },
                        { value: 'unsubscribed', label: t('newsletter.unsubscribed') },
                    ],
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="subscribers.data"
                row-key="id"
                :empty-message="t('newsletter.empty')"
            >
                <template #cell.email="{ row }">
                    <span class="text-body-s text-cool-100">{{ row.email }}</span>
                </template>

                <template #cell.joined="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ formatDateTime(row.joinedAt) }}</span>
                        <span v-if="row.leftAt" class="text-body-xs text-cool-60">
                            {{ t('newsletter.left') }} · {{ formatDateTime(row.leftAt) }}
                        </span>
                    </span>
                </template>

                <!-- Sakelar di dalam sel memakai `hide-label`: labelnya tetap
                     ditulis untuk pembaca layar, tapi tidak dicetak ulang di
                     tiap baris di samping judul kolom yang sudah ada. -->
                <template #cell.subscribed="{ row }">
                    <AppToggle
                        :model-value="row.isSubscribed"
                        hide-label
                        :label="t('newsletter.subscribed')"
                        @update:model-value="toggle(row as Row, $event)"
                    />
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.email })">
                        <button
                            type="button"
                            class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-danger hover:bg-cool-10"
                            @click="removing = row as Row"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </RowMenu>
                </template>
            </DataTable>

            <AppPagination
                :current-page="subscribers.current_page"
                :last-page="subscribers.last_page"
                :href-for="(n) => `/newsletter?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('newsletter.delete_title')"
            :description="t('newsletter.delete_body', { name: removing?.email ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
