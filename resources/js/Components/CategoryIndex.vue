<script setup lang="ts">
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhPlus } from '@phosphor-icons/vue'

import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppPagination from '@/Components/AppPagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import type { Crumb, Paginated, TableColumn } from '@/types'

/**
 * Daftar kategori — dipakai News Category dan FAQ Category.
 *
 * Kedua layar itu identik kecuali judul, breadcrumb, dan URL-nya. Menyalinnya
 * berarti dua berkas yang akan berbeda perlahan: perbaikan di satu tempat
 * berhenti sampai di situ.
 */
interface Row {
    id: number
    name: string
    usage: number
    isActive: boolean
}

const props = defineProps<{
    title: string
    breadcrumbs: Crumb[]
    baseUrl: string
    categories: Paginated<Row>
    filters: { q: string; status: string }
    /** Layar urutkan hanya ada kalau modulnya memang punya. */
    manageUrl?: string
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters(props.baseUrl, {
    q: props.filters.q,
    status: props.filters.status,
})

const columns: TableColumn[] = [
    { key: 'name', label: t('category.name') },
    { key: 'usage', label: t('category.usage') },
    { key: 'status', label: t('common.status') },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function toggle(row: Row): void {
    router.put(
        `${props.baseUrl}/${row.id}`,
        { name: row.name, is_active: !row.isActive },
        { preserveScroll: true },
    )
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`${props.baseUrl}/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <PageHeader :title="title" :breadcrumbs="breadcrumbs">
        <template #actions>
            <AppButton v-if="manageUrl" :href="manageUrl" variant="outline">{{ t('category.manage_title') }}</AppButton>
            <AppButton :href="`${baseUrl}/create`">
                <template #iconLeft><PhPlus :size="24" /></template>
                {{ t('category.add') }}
            </AppButton>
        </template>
    </PageHeader>

    <FilterBar
        v-model:search="state.q"
        :filters="[
            {
                key: 'status',
                label: 'Status',
                value: state.status,
                options: [
                    { value: 'active', label: 'Active' },
                    { value: 'inactive', label: 'Inactive' },
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
            :rows="categories.data"
            row-key="id"
            :empty-message="t('category.empty')"
        >
            <template #cell.status="{ row }">
                <AppToggle
                    :model-value="row.isActive"
                    :label="`${t('common.status')} ${row.name}`"
                    @update:model-value="toggle(row)"
                />
            </template>

            <template #cell.actions="{ row }">
                <RowMenu :label="t('common.row_actions', { name: row.name })">
                    <Link
                        :href="`${baseUrl}/${row.id}/edit`"
                        class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                    >
                        {{ t('common.edit') }}
                    </Link>
                    <button
                        type="button"
                        class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-danger hover:bg-cool-10"
                        @click="removing = row"
                    >
                        {{ t('common.delete') }}
                    </button>
                </RowMenu>
            </template>
        </DataTable>

        <AppPagination
            :current-page="categories.current_page"
            :last-page="categories.last_page"
            :href-for="(n) => `${baseUrl}?page=${n}`"
            @navigate="go($event)"
        />
    </div>

    <ConfirmDialog
        :open="removing !== null"
        variant="deletion"
        :title="t('category.delete_title')"
        :description="t('category.delete_body', { name: removing?.name ?? '' })"
        :confirm-label="t('common.delete')"
        :processing="processing"
        @confirm="destroy"
        @cancel="removing = null"
    />
</template>
