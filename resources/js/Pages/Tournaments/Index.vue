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
import AppPagination from '@/Components/AppPagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import VisibilitySelect from '@/Components/VisibilitySelect.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDate } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    coverage: string
    location: string
    startsOn: string | null
    endsOn: string | null
    stage: string
    registrationState: string
    visibility: string
    scheduledFor: string | null
    canSchedule: boolean
    publishedAt: string | null
    publishedBy: string | null
    updatedAt: string | null
    updatedBy: string | null
    notifyCount: number
}

const props = defineProps<{
    tournaments: Paginated<Row>
    coverages: string[]
    filters: { q: string; status: string; coverage: string; stage: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/tournaments', {
    q: props.filters.q,
    status: props.filters.status,
    coverage: props.filters.coverage,
    stage: props.filters.stage,
})

/*
 * Layar daftar TIDAK ada di file desain — node `585:11241` hanya menggambar
 * Add Tournament. Susunannya karena itu meminjam pola yang sudah dipakai
 * Documents (`369:5236`) dan Gallery (`478:5884`): identitas, Visibility di
 * dalam sel, lalu "siapa + kapan".
 *
 * Dua kolom yang khas turnamen: "Stage" (akan datang / berlangsung / selesai)
 * dan "Registration". Keduanya diturunkan dari tanggal, bukan disimpan — lihat
 * `Tournament::getStageAttribute()`.
 */
const columns: TableColumn[] = [
    { key: 'name', label: t('tournaments.name') },
    { key: 'visibility', label: t('news.visibility'), width: '200px' },
    { key: 'dates', label: t('tournaments.dates'), width: '190px' },
    { key: 'stage', label: t('tournaments.stage'), width: '130px' },
    { key: 'registration', label: t('tournaments.registration_state'), width: '140px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function setVisibility(row: Row, status: string): void {
    router.patch(`/tournaments/${row.id}/visibility`, { status }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/tournaments/export' : `/tournaments/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/tournaments/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('tournaments.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('tournaments.list_title')"
            :breadcrumbs="[{ label: t('tournaments.title') }, { label: t('tournaments.list') }]"
        >
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/tournaments/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('tournaments.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('tournaments.search_placeholder')"
            :filters="[
                {
                    key: 'status',
                    label: t('news.visibility'),
                    value: state.status,
                    options: [
                        { value: 'published', label: t('news.status_posted') },
                        { value: 'scheduled', label: t('news.status_scheduled') },
                        { value: 'draft', label: t('news.status_draft') },
                        { value: 'unpublished', label: t('news.status_unpublished') },
                    ],
                },
                {
                    key: 'stage',
                    label: t('tournaments.stage'),
                    value: state.stage,
                    options: [
                        { value: 'upcoming', label: t('tournaments.stage_upcoming') },
                        { value: 'live', label: t('tournaments.stage_live') },
                        { value: 'completed', label: t('tournaments.stage_completed') },
                    ],
                },
                {
                    key: 'coverage',
                    label: t('tournaments.coverage_column'),
                    value: state.coverage,
                    options: coverages.map((c) => ({ value: c, label: c })),
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="tournaments.data"
                row-key="id"
                :empty-message="t('tournaments.empty')"
            >
                <template #cell.name="{ row }">
                    <span class="flex flex-col">
                        <Link
                            :href="`/tournaments/${row.id}/edit`"
                            class="block max-w-[320px] text-body-s text-cool-100 underline decoration-cool-30 underline-offset-4 transition-colors hover:text-primary-90"
                        >
                            {{ row.name }}
                        </Link>
                        <span class="truncate text-body-xs text-cool-60">
                            {{ row.coverage }} · {{ row.location }}
                        </span>
                    </span>
                </template>

                <template #cell.visibility="{ row }">
                    <VisibilitySelect
                        :value="row.visibility"
                        :name="row.name"
                        :scheduled-for="row.scheduledFor"
                        :can-schedule="row.canSchedule"
                        @select="setVisibility(row as Row, $event)"
                    />
                </template>

                <template #cell.dates="{ row }">
                    <span class="text-body-s text-cool-70">
                        {{ formatDate(row.startsOn) }} – {{ formatDate(row.endsOn) }}
                    </span>
                </template>

                <!-- Warna selalu berpasangan dengan katanya — tidak ada keadaan
                     yang dibedakan hanya oleh warna. -->
                <template #cell.stage="{ row }">
                    <span
                        class="inline-flex border px-2 py-1 text-body-xs"
                        :class="{
                            'border-primary-60 bg-transparent text-cool-90': row.stage === 'live',
                            'border-cool-30 bg-transparent text-cool-70': row.stage === 'upcoming',
                            'border-transparent bg-cool-10 text-cool-60': row.stage === 'completed',
                        }"
                    >
                        {{ t(`tournaments.stage_${row.stage}`) }}
                    </span>
                </template>

                <template #cell.registration="{ row }">
                    <span class="text-body-s text-cool-70">
                        {{ t(`tournaments.registration_${row.registrationState}`) }}
                    </span>
                </template>

                <template #cell.updated="{ row }">
                    <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.name })">
                        <!-- Daftar "Notify me" tinggal di menu baris, bukan jadi
                             kolom kedelapan: yang dicari orang di sini tindakan
                             ("unduh alamatnya"), bukan angka yang dipindai
                             sambil menggulir. Mati DENGAN alasannya saat kosong
                             — tombol yang menurunkan berkas nol baris memberi
                             tahu terlambat. -->
                        <a
                            v-if="row.notifyCount > 0"
                            :href="`/tournaments/${row.id}/notifications/export`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('tournaments.notify_export', { count: row.notifyCount }) }}
                        </a>
                        <span
                            v-else
                            class="block cursor-not-allowed px-4 py-2 text-body-s text-cool-40"
                            :title="t('tournaments.notify_empty')"
                        >
                            {{ t('tournaments.notify_export', { count: 0 }) }}
                        </span>

                        <Link
                            :href="`/tournaments/${row.id}/edit`"
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
                :current-page="tournaments.current_page"
                :last-page="tournaments.last_page"
                :href-for="(n) => `/tournaments?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('tournaments.delete_title')"
            :description="t('tournaments.delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
