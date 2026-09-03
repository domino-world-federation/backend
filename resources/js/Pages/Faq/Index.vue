<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhDownloadSimple, PhPlus } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppPagination from '@/Components/AppPagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    question: string
    category: string | null
    pages: string[]
    isActive: boolean
    createdAt: string | null
    updatedAt: string | null
    updatedBy: string | null
}

const props = defineProps<{
    faqs: Paginated<Row>
    categories: Array<{ value: number; label: string }>
    filters: { q: string; status: string; category: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/faq', {
    q: props.filters.q,
    status: props.filters.status,
    category: props.filters.category,
})

// Susunannya mengikuti News: identitas, konteks, lalu "siapa + kapan",
// sakelar, dan menu baris.
const columns: TableColumn[] = [
    { key: 'question', label: t('faq.question') },
    { key: 'category', label: t('common.category') },
    { key: 'pages', label: t('faq.applied_to') },
    { key: 'created', label: t('news.created') },
    { key: 'updated', label: t('news.last_modified') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

/** Sakelar status disimpan seketika — tidak perlu membuka formulirnya. */
function setStatus(row: Row, value: boolean): void {
    router.patch(`/faq/${row.id}/status`, { is_active: value }, {
        preserveScroll: true,
        preserveState: true,
    })
}

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di News. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/faq/export' : `/faq/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/faq/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('faq.list')" />

    <AdminLayout>
        <PageHeader :title="t('faq.list')" :breadcrumbs="[{ label: t('faq.title') }, { label: t('faq.list') }]">
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/faq/categories" variant="outline">{{ t('news.manage_category') }}</AppButton>
                <AppButton href="/faq/pages" variant="outline">{{ t('faq.pages_link') }}</AppButton>
                <AppButton href="/faq/manage" variant="outline">{{ t('faq.order_title') }}</AppButton>
                <AppButton href="/faq/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('faq.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :filters="[
                {
                    key: 'status',
                    label: t('common.status'),
                    value: state.status,
                    options: [
                        { value: 'active', label: t('common.active') },
                        { value: 'inactive', label: t('common.inactive') },
                    ],
                },
                {
                    key: 'category',
                    label: t('common.category'),
                    value: state.category,
                    options: categories.map((c) => ({ value: String(c.value), label: c.label })),
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="faqs.data"
                row-key="id"
                :empty-message="t('faq.empty')"
            >
                <!-- Pertanyaannya tautan ke layar baca — sama seperti judul
                     berita di News. -->
                <template #cell.question="{ row }">
                    <Link
                        :href="`/faq/${row.id}`"
                        class="block max-w-[320px] text-body-s text-cool-100 underline decoration-cool-30 underline-offset-4 transition-colors hover:text-primary-90"
                        :title="t('news.view', { name: row.question })"
                    >
                        {{ row.question }}
                    </Link>
                </template>

                <template #cell.created="{ row }">
                    <PersonStamp :at="row.createdAt" />
                </template>

                <template #cell.updated="{ row }">
                    <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
                </template>

                <template #cell.category="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.category ?? t('common.none') }}</span>
                </template>

                <template #cell.pages="{ row }">
                    <span v-if="row.pages.length === 0" class="text-body-s text-cool-60">—</span>
                    <span v-else class="flex flex-wrap gap-1">
                        <span
                            v-for="page in row.pages"
                            :key="page"
                            class="border border-cool-30 px-2 py-0.5 text-body-xs text-cool-70"
                        >
                            {{ page }}
                        </span>
                    </span>
                </template>

                <template #cell.status="{ row }">
                    <AppToggle
                        :model-value="row.isActive"
                        :label="`${t('common.status')} ${row.question}`"
                        hide-label
                        @update:model-value="setStatus(row as Row, $event)"
                    />
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.question })">
                        <Link
                            :href="`/faq/${row.id}`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('faq.detail') }}
                        </Link>
                        <Link
                            :href="`/faq/${row.id}/edit`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('common.edit') }}
                        </Link>
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
                :current-page="faqs.current_page"
                :last-page="faqs.last_page"
                :href-for="(n) => `/faq?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('faq.delete_title')"
            :description="t('faq.delete_body', { name: removing?.question ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
