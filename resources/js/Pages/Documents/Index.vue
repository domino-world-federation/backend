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
import VisibilitySelect from '@/Components/VisibilitySelect.vue'
import AppPagination from '@/Components/AppPagination.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import type { DocumentCategory, Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    title: string
    category: string | null
    fileSize: string
    fileUrl: string | null
    visibility: string
    scheduledFor: string | null
    canSchedule: boolean
    publishedAt: string | null
    publishedBy: string | null
    createdAt: string | null
    createdBy: string | null
    updatedAt: string | null
    updatedBy: string | null
}

const props = defineProps<{
    documents: Paginated<Row>
    categories: DocumentCategory[]
    filters: { q: string; status: string; category: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/documents', {
    q: props.filters.q,
    status: props.filters.status,
    category: props.filters.category,
})

// Kolom persis `369:5236`. Kolom "View" (lebar 55 di desain) adalah tautan ke
// berkasnya — satu-satunya tempat di layar ini yang benar-benar membuka PDF-nya.
const columns: TableColumn[] = [
    { key: 'title', label: t('documents.doc_title') },
    { key: 'visibility', label: t('news.visibility'), width: '200px' },
    { key: 'category', label: t('common.category') },
    { key: 'published', label: t('gallery.published'), width: '180px' },
    { key: 'created', label: t('news.created'), width: '180px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'view', label: '', width: '70px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

/** Ubah visibilitas langsung dari barisnya — tidak perlu membuka formulirnya. */
function setVisibility(row: Row, status: string): void {
    router.patch(`/documents/${row.id}/visibility`, { status }, {
        preserveScroll: true,
        preserveState: true,
    })
}

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di News. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/documents/export' : `/documents/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/documents/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('documents.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('documents.list_title')"
            :breadcrumbs="[{ label: t('documents.title') }, { label: t('documents.list') }]"
        >
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/documents/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('documents.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
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
                    key: 'category',
                    label: t('common.category'),
                    value: state.category,
                    options: categories.map((c) => ({ value: c.value, label: c.label })),
                },
            ]"
            @change="set"
        />

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="documents.data"
                row-key="id"
                :empty-message="t('documents.empty')"
            >
                <!-- Judulnya menuju layar BACA, bukan langsung ke PDF-nya.
                     Membuka berkas di tab baru adalah kejutan; unduhannya ada
                     di layar detail, di sebelah keterangan yang menjelaskan
                     berkas mana yang akan terbuka. -->
                <template #cell.title="{ row }">
                    <span class="flex flex-col">
                        <Link
                            :href="`/documents/${row.id}`"
                            class="block max-w-[320px] text-body-s text-cool-100 underline decoration-cool-30 underline-offset-4 transition-colors hover:text-primary-90"
                            :title="t('news.view', { name: row.title })"
                        >
                            {{ row.title }}
                        </Link>
                        <!-- Desain menggambar satu teks saja di sel ini
                             (`478:5341`). Ukuran berkas tetap dicetak sebagai
                             subteks karena layar Add-nya sendiri bicara soal
                             batas ukuran ("maximum 10 MB"), dan angka itu
                             paling dicari tepat di sebelah tautan View. -->
                        <span class="text-body-xs text-cool-60">{{ row.fileSize }}</span>
                    </span>
                </template>

                <template #cell.category="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.category ?? t('common.none') }}</span>
                </template>

                <template #cell.visibility="{ row }">
                    <VisibilitySelect
                        :value="row.visibility"
                        :name="row.title"
                        :scheduled-for="row.scheduledFor"
                        :can-schedule="row.canSchedule"
                        :edit-href="`/documents/${row.id}/edit`"
                        @select="setVisibility(row as Row, $event)"
                    />
                </template>

                <!-- Draft belum pernah tayang, jadi kolomnya kosong — bukan
                     tanggal unggah yang dipinjam. Tanggal tayang yang mendahului
                     penayangannya adalah angka yang berbohong. -->
                <template #cell.published="{ row }">
                    <PersonStamp v-if="row.publishedAt" :name="row.publishedBy" :at="row.publishedAt" />
                    <span v-else class="text-body-xs text-cool-60">{{ t('common.none') }}</span>
                </template>

                <template #cell.created="{ row }">
                    <PersonStamp :name="row.createdBy" :at="row.createdAt" />
                </template>

                <template #cell.updated="{ row }">
                    <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
                </template>

                <!-- Kolom "View" (`478:5381`) — navy, membuka berkasnya di tab
                     baru. Judul barisnya menuju layar BACA; ini satu-satunya
                     kontrol yang benar-benar membuka PDF-nya, dan karena itu ia
                     `target="_blank"`: mengganti halaman backoffice dengan
                     penampil PDF berarti orang kehilangan tempatnya di daftar. -->
                <template #cell.view="{ row }">
                    <a
                        v-if="row.fileUrl"
                        :href="row.fileUrl"
                        target="_blank"
                        rel="noopener"
                        class="text-body-s text-primary-90 underline underline-offset-2"
                    >
                        {{ t('documents.view') }}
                    </a>
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.title })">
                        <Link
                            :href="`/documents/${row.id}`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('documents.detail') }}
                        </Link>
                        <Link
                            :href="`/documents/${row.id}/edit`"
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
                :current-page="documents.current_page"
                :last-page="documents.last_page"
                :href-for="(n) => `/documents?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('documents.delete_title')"
            :description="t('documents.delete_body', { name: removing?.title ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
