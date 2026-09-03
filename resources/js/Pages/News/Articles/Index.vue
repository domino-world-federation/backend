<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhDownloadSimple, PhPlus, PhStar } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppToggle from '@/Components/AppToggle.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import VisibilitySelect from '@/Components/VisibilitySelect.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    title: string
    category: string | null
    status: string
    visibility: string
    scheduledFor: string | null
    canSchedule: boolean
    isHighlighted: boolean
    publishedAt: string | null
    publishedBy: string | null
    createdAt: string | null
    createdBy: string | null
    updatedAt: string | null
    updatedBy: string | null
}

const props = defineProps<{
    articles: Paginated<Row>
    categories: Array<{ value: number; label: string }>
    filters: { q: string; status: string; category: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/news', {
    q: props.filters.q,
    status: props.filters.status,
    category: props.filters.category,
})

/*
 * Kolomnya persis Figma `252:1743`: judul, visibilitas, lalu TIGA kolom
 * "siapa + kapan", highlight, dan menu baris.
 *
 * Kategori sengaja TIDAK jadi kolom sendiri — ia sudah jadi filter di atas
 * tabel, dan tujuh kolom di layar 1440 membuat judul beritanya terpotong jadi
 * dua kata. Yang dicari orang di daftar ini adalah judul dan keadaannya.
 */
const columns: TableColumn[] = [
    { key: 'title', label: t('news.field_title') },
    { key: 'visibility', label: t('news.visibility') },
    { key: 'published', label: t('news.posted') },
    { key: 'created', label: t('news.created') },
    { key: 'updated', label: t('news.last_modified') },
    { key: 'highlight', label: t('news.highlight_badge') },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/news/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}

/*
 * Dua sakelar cepat. Keduanya `preserveScroll` DAN `preserveState`: mengubah
 * status baris ketujuh tidak boleh melempar orang kembali ke puncak tabel,
 * dan tidak boleh menutup filter yang sedang dipakainya.
 */
function setVisibility(row: Row, status: string): void {
    router.patch(`/news/${row.id}/visibility`, { status }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function setHighlight(row: Row, value: boolean): void {
    router.patch(`/news/${row.id}/highlight`, { is_highlighted: value }, {
        preserveScroll: true,
        preserveState: true,
    })
}

/** Ekspor mengikuti filter yang sedang aktif — lihat `export()` di controller. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/news/export' : `/news/export?${query}`
}
</script>

<template>
    <Head :title="t('news.list')" />

    <AdminLayout>
        <PageHeader :title="t('news.list')" :breadcrumbs="[{ label: t('news.title') }, { label: t('news.list') }]">
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/news/categories" variant="outline">{{ t('news.manage_category') }}</AppButton>
                <AppButton href="/news/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('news.add') }}
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
                        { value: 'published', label: t('news.status_posted') },
                        { value: 'scheduled', label: t('news.status_scheduled') },
                        { value: 'draft', label: t('news.status_draft') },
                        { value: 'unpublished', label: t('news.status_unpublished') },
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
                :rows="articles.data"
                row-key="id"
                :empty-message="t('news.empty')"
            >
                <!-- Judulnya tautan ke layar baca. Membaca satu artikel adalah
                     hal paling sering dilakukan di daftar ini, dan sebelumnya
                     satu-satunya jalan ke sana adalah membuka formulir
                     suntingnya — memeriksa isi tidak seharusnya dimulai dengan
                     membuka sesuatu yang bisa diubah tanpa sadar. -->
                <template #cell.title="{ row }">
                    <Link
                        :href="`/news/${row.id}`"
                        class="flex max-w-[280px] items-start gap-2 text-body-s text-cool-100 transition-colors hover:text-primary-90"
                        :title="t('news.view', { name: row.title })"
                    >
                        <PhStar
                            v-if="row.isHighlighted"
                            :size="16"
                            weight="fill"
                            class="mt-0.5 shrink-0 text-primary-60"
                            aria-hidden="true"
                        />
                        <span class="underline decoration-cool-30 underline-offset-4">
                            {{ row.title }}
                        </span>
                    </Link>
                </template>

                <template #cell.visibility="{ row }">
                    <VisibilitySelect
                        :value="row.visibility"
                        :name="row.title"
                        :scheduled-for="row.scheduledFor"
                        :can-schedule="row.canSchedule"
                        @select="setVisibility(row as Row, $event)"
                    />
                </template>

                <template #cell.published="{ row }">
                    <PersonStamp :name="row.publishedBy" :at="row.publishedAt" />
                </template>

                <template #cell.created="{ row }">
                    <PersonStamp :name="row.createdBy" :at="row.createdAt" />
                </template>

                <template #cell.updated="{ row }">
                    <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
                </template>

                <template #cell.highlight="{ row }">
                    <AppToggle
                        :model-value="row.isHighlighted"
                        :label="t('news.toggle_highlight', { name: row.title })"
                        hide-label
                        @update:model-value="setHighlight(row as Row, $event)"
                    />
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.title })">
                        <Link
                            :href="`/news/${row.id}`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('news.detail') }}
                        </Link>
                        <Link
                            :href="`/news/${row.id}/edit`"
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
                :current-page="articles.current_page"
                :last-page="articles.last_page"
                :href-for="(n) => `/news?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('news.delete_title')"
            :description="t('news.delete_body', { name: removing?.title ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
