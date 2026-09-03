<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhMedal, PhTrophy } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import ContextNote from '@/Components/ContextNote.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDate } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    location: string
    endsOn: string | null
    winnerCount: number
}

const props = defineProps<{
    tournaments: Paginated<Row>
    filters: { q: string }
}>()

const { t } = useI18n()

const { state, go, loading } = useIndexFilters('/results', { q: props.filters.q })

const columns: TableColumn[] = [
    { key: 'name', label: t('tournaments.name') },
    { key: 'ended', label: t('results.ended'), width: '160px' },
    { key: 'winners', label: t('results.winner_count'), width: '120px' },
    { key: 'actions', label: '', width: '170px', align: 'right' },
]
</script>

<template>
    <Head :title="t('results.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('results.list_title')"
            :breadcrumbs="[{ label: t('results.title') }, { label: t('results.list') }]"
        >
            <template #actions>
                <AppButton href="/results/champions" variant="outline">
                    <template #iconLeft><PhTrophy :size="24" /></template>
                    {{ t('results.champions') }}
                </AppButton>
                <AppButton href="/results/olympic" variant="outline">
                    <template #iconLeft><PhMedal :size="24" /></template>
                    {{ t('results.olympic') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('results.search_placeholder')"
            :filters="[]"
        />

        <!-- Turnamen yang belum selesai sengaja tidak muncul: menawarkan
             formulir hasil untuk pertandingan yang belum dimainkan mengundang
             orang mengarang angka. -->
        <ContextNote>{{ t('results.context_note') }}</ContextNote>

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="tournaments.data"
                row-key="id"
                :empty-message="t('results.empty')"
            >
                <template #cell.name="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ row.name }}</span>
                        <span class="text-body-xs text-cool-60">{{ row.location }}</span>
                    </span>
                </template>

                <template #cell.ended="{ row }">
                    <span class="text-body-s text-cool-70">{{ formatDate(row.endsOn) }}</span>
                </template>

                <!-- Nol dicetak berbeda: itu bukan angka, itu pekerjaan yang
                     belum dilakukan. -->
                <template #cell.winners="{ row }">
                    <span
                        class="inline-flex border px-2 py-1 text-body-xs"
                        :class="
                            row.winnerCount > 0
                                ? 'border-transparent bg-cool-10 text-cool-90'
                                : 'border-primary-60 bg-transparent text-cool-90'
                        "
                    >
                        {{ row.winnerCount }}
                    </span>
                </template>

                <template #cell.actions="{ row }">
                    <Link
                        :href="`/results/${row.id}`"
                        class="text-body-s text-primary-90 underline underline-offset-2"
                    >
                        {{ t('results.manage') }}
                    </Link>
                </template>
            </DataTable>

            <AppPagination
                :current-page="tournaments.current_page"
                :last-page="tournaments.last_page"
                :href-for="(n) => `/results?page=${n}`"
                @navigate="go($event)"
            />
        </div>
    </AdminLayout>
</template>
