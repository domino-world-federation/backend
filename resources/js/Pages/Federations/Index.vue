<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhChartBar, PhDownloadSimple, PhPlus } from '@phosphor-icons/vue'

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
import ContextNote from '@/Components/ContextNote.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    country: string
    flagUrl: string | null
    tier: string | null
    tierLabel: string | null
    joinedYear: number | null
    president: string | null
    isActive: boolean
    adminCount: number
    updatedAt: string | null
    updatedBy: string | null
}

const props = defineProps<{
    federations: Paginated<Row>
    tiers: Array<{ value: string; label: string }>
    filters: { q: string; status: string; tier: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/federations', {
    q: props.filters.q,
    status: props.filters.status,
    tier: props.filters.tier,
})

const columns: TableColumn[] = [
    { key: 'name', label: t('federations.name') },
    { key: 'tier', label: t('federations.tier'), width: '170px' },
    { key: 'president', label: t('federations.president') },
    { key: 'admins', label: t('federations.admins'), width: '90px' },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function setStatus(row: Row, value: boolean): void {
    router.patch(`/federations/${row.id}/status`, { is_active: value }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/federations/export' : `/federations/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/federations/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('federations.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('federations.list_title')"
            :breadcrumbs="[{ label: t('federations.title') }, { label: t('federations.list') }]"
        >
            <template #actions>
                <AppButton href="/federations/stats" variant="outline">
                    <template #iconLeft><PhChartBar :size="24" /></template>
                    {{ t('federations.stats') }}
                </AppButton>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/federations/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('federations.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('federations.search_placeholder')"
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
                    key: 'tier',
                    label: t('federations.tier'),
                    value: state.tier,
                    options: tiers,
                },
            ]"
            @change="set"
        />

        <ContextNote>{{ t('federations.context_note') }}</ContextNote>

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="federations.data"
                row-key="id"
                :empty-message="t('federations.empty')"
            >
                <!-- Bendera, nama, lalu negara dan tahun bergabung sebagai
                     subteks. Benderanya opsional dan itu keadaan NORMAL: desain
                     situs publik menggambar semua baris dengan kotak abu yang
                     sama, jadi yang kosong jatuh ke kotak polos, bukan galat. -->
                <template #cell.name="{ row }">
                    <Link
                        :href="`/federations/${row.id}/edit`"
                        class="flex items-center gap-3 transition-colors hover:text-primary-90"
                    >
                        <span class="flex h-6 w-9 shrink-0 items-center justify-center overflow-hidden bg-cool-10">
                            <img
                                v-if="row.flagUrl"
                                :src="row.flagUrl"
                                :alt="row.country"
                                class="size-full object-cover"
                            />
                        </span>

                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-body-s text-cool-100 underline decoration-cool-30 underline-offset-4">
                                {{ row.name }}
                            </span>
                            <span class="truncate text-body-xs text-cool-60">
                                {{ row.country }}
                                <template v-if="row.joinedYear"> · {{ row.joinedYear }}</template>
                            </span>
                        </span>
                    </Link>
                </template>

                <template #cell.tier="{ row }">
                    <span
                        v-if="row.tierLabel"
                        class="inline-flex border border-cool-30 px-2 py-1 text-body-xs text-cool-70"
                    >
                        {{ row.tierLabel }}
                    </span>
                    <span v-else class="text-body-xs text-cool-60">{{ t('common.none') }}</span>
                </template>

                <template #cell.president="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.president ?? t('common.none') }}</span>
                </template>

                <template #cell.admins="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.adminCount }}</span>
                </template>

                <template #cell.status="{ row }">
                    <AppToggle
                        :model-value="row.isActive"
                        :label="`${t('common.status')} ${row.name}`"
                        hide-label
                        @update:model-value="setStatus(row as Row, $event)"
                    />
                </template>

                <template #cell.updated="{ row }">
                    <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.name })">
                        <Link
                            :href="`/federations/${row.id}/edit`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('common.edit') }}
                        </Link>
                        <!-- Tombol hapus hilang selama federasinya masih jadi
                             lingkup akun admin: server menolaknya, dan tombol
                             yang berujung galat cuma mengajari orang
                             mengabaikannya. -->
                        <button
                            v-if="row.adminCount === 0"
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
                :current-page="federations.current_page"
                :last-page="federations.last_page"
                :href-for="(n) => `/federations?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('federations.delete_title')"
            :description="t('federations.delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
