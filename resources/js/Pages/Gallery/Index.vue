<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhDownloadSimple, PhFilmSlate, PhPlus } from '@phosphor-icons/vue'

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
import type { Paginated, TableColumn } from '@/types'

interface Item {
    id: number
    kind: string
    url: string | null
    alt: string | null
    event: string | null
    eventType: string | null
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
    items: Paginated<Item>
    filters: { q: string; status: string; category: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/gallery', {
    q: props.filters.q,
    status: props.filters.status,
    category: props.filters.category,
})

/*
 * Kolom persis `478:5884` — dan ini MEMBALIK keputusan lama.
 *
 * Galeri sempat digambar sebagai kisi dengan alasan "yang dibandingkan orang di
 * sini gambarnya" (penyimpangan P10 di docs/PROGRESS.md). Desainnya ternyata
 * memang tabel, lengkap dengan tiga kolom pelaku yang tidak muat di kartu —
 * dan pertanyaan yang dijawab layar ini bukan "mana yang paling bagus"
 * melainkan "mana yang tayang, sejak kapan, oleh siapa". Thumbnail-nya tetap
 * ada, sebagai bagian dari sel Image Info.
 */
const columns: TableColumn[] = [
    { key: 'image', label: t('gallery.image_info') },
    { key: 'visibility', label: t('news.visibility'), width: '200px' },
    { key: 'published', label: t('gallery.published'), width: '190px' },
    { key: 'created', label: t('news.created'), width: '190px' },
    { key: 'updated', label: t('news.last_modified'), width: '190px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Item | null>(null)
const processing = ref(false)

/**
 * Ubah visibilitas langsung dari barisnya — tidak perlu membuka formulirnya.
 *
 * Desain menaruh pemilihnya DI DALAM sel (`478:5921`: ikon globe, teks,
 * chevron), bukan di menu titik-tiga.
 */
function setVisibility(item: Item, status: string): void {
    router.patch(`/gallery/${item.id}/visibility`, { status }, {
        preserveScroll: true,
        preserveState: true,
    })
}

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di News. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/gallery/export' : `/gallery/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/gallery/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('gallery.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('gallery.list_title')"
            :breadcrumbs="[{ label: t('gallery.title') }, { label: t('gallery.list') }]"
        >
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/gallery/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('gallery.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <!-- Dua dropdown, persis desain: Visibility dan Category. Penyaring
             "event tertentu" yang dulu ada pindah ke kotak pencarian — ia
             menjangkau nama event, bukan cuma alt text. -->
        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('gallery.search_placeholder')"
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
                    options: [
                        { value: 'event', label: t('gallery.type_event') },
                        { value: 'tournament', label: t('gallery.type_tournament') },
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
                :rows="items.data"
                row-key="id"
                :empty-message="t('gallery.empty')"
            >
                <!-- Sel "Image Info" — thumbnail, nama event, lalu jenisnya
                     sebagai subteks (`478:6533`). Thumbnail-nya kecil dan itu
                     memang cukup: di layar ini gambarnya penanda baris, bukan
                     yang sedang dinilai. -->
                <template #cell.image="{ row }">
                    <Link
                        :href="`/gallery/${row.id}/edit`"
                        class="flex items-center gap-3 transition-colors hover:text-primary-90"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden bg-cool-10">
                            <img
                                v-if="row.kind === 'image' && row.url"
                                :src="row.url"
                                :alt="row.alt ?? ''"
                                class="size-full object-cover"
                            />
                            <PhFilmSlate v-else :size="20" class="text-cool-40" aria-hidden="true" />
                        </span>

                        <span class="flex min-w-0 flex-col">
                            <span class="truncate text-body-s text-cool-90">
                                {{ row.alt || row.event || t('common.none') }}
                            </span>
                            <span class="truncate text-body-xs text-cool-60">
                                {{ row.event ?? t('common.none') }} ·
                                {{ t(`gallery.type_${row.eventType}`) }}
                            </span>
                        </span>
                    </Link>
                </template>

                <template #cell.visibility="{ row }">
                    <VisibilitySelect
                        :value="row.visibility"
                        :name="row.alt ?? String(row.id)"
                        :scheduled-for="row.scheduledFor"
                        :can-schedule="row.canSchedule"
                        :edit-href="`/gallery/${row.id}/edit`"
                        @select="setVisibility(row as Item, $event)"
                    />
                </template>

                <!-- Draft belum pernah tayang, jadi kolomnya kosong — bukan
                     tanggal pembuatan yang dipinjam. Tanggal tayang yang
                     mendahului penayangannya adalah angka yang berbohong. -->
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

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.alt ?? String(row.id) })">
                        <Link
                            :href="`/gallery/${row.id}/edit`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('common.edit') }}
                        </Link>
                        <button
                            type="button"
                            class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-danger hover:bg-cool-10"
                            @click="removing = row as Item"
                        >
                            {{ t('common.delete') }}
                        </button>
                    </RowMenu>
                </template>
            </DataTable>

            <AppPagination
                :current-page="items.current_page"
                :last-page="items.last_page"
                :href-for="(n) => `/gallery?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('gallery.delete_title')"
            :description="t('gallery.delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
