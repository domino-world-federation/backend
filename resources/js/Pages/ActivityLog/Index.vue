<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import FilterBar from '@/Components/FilterBar.vue'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import ContextNote from '@/Components/ContextNote.vue'
import AppPagination from '@/Components/AppPagination.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { PhDownloadSimple } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Change {
    field: string
    from: string | null
    to: string | null
}

interface Row {
    id: number
    module: string
    moduleLabel: string
    event: string
    eventLabel: string
    /** Nama baris yang diubah — bukan `#id`. Lihat `subjectLabel()` di controller. */
    subject: string
    subjectId: number | string | null
    causer: string
    causerEmail: string | null
    at: string | null
    ip: string | null
    /** Ringkasan terbaca; `userAgent` menyimpan aslinya. */
    device: string | null
    userAgent: string | null
    result: string
    changes: Change[]
}

const props = defineProps<{
    entries: Paginated<Row>
    modules: Array<{ value: string; label: string }>
    events: Array<{ value: string; label: string }>
    causers: Array<{ value: string; label: string }>
    filters: {
        module: string
        event: string
        causer: string
        result: string
        q: string
        from: string
        to: string
    }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/activity-log', {
    module: props.filters.module,
    event: props.filters.event,
    causer: props.filters.causer,
    result: props.filters.result,
    q: props.filters.q,
    from: props.filters.from,
    to: props.filters.to,
})

/** Ekspor mengikuti filter yang sedang aktif — sama seperti di News. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/activity-log/export' : `/activity-log/export?${query}`
}

// Kolom `528:11529`: Time | Actor | Event | Target | IP Address | Result.
// "Changes" tetap ada di ujung — ia bukan bagian desain, tapi ia satu-satunya
// tempat jejak audit ini menyimpan NILAI sebelum dan sesudah, dan membuangnya
// akan mengubah log audit jadi daftar judul kejadian.
const columns: TableColumn[] = [
    { key: 'at', label: t('activity_log.when'), width: '150px' },
    { key: 'causer', label: t('activity_log.actor') },
    { key: 'event', label: t('activity_log.event') },
    { key: 'what', label: t('activity_log.target') },
    { key: 'origin', label: t('activity_log.ip'), width: '170px' },
    { key: 'result', label: t('activity_log.result'), width: '110px' },
    { key: 'changes', label: t('activity_log.changes'), width: '140px' },
]

</script>

<template>
    <Head :title="t('activity_log.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('activity_log.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('activity_log.title') }]"
        >
            <template #description>{{ t('activity_log.intro') }}</template>
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('activity_log.search_placeholder')"
            :filters="[
                {
                    key: 'module',
                    label: t('activity_log.module'),
                    value: state.module,
                    options: modules,
                },
                {
                    key: 'event',
                    label: t('activity_log.event'),
                    value: state.event,
                    options: events,
                },
                {
                    key: 'causer',
                    label: t('activity_log.actor'),
                    value: state.causer,
                    options: causers,
                },
                {
                    key: 'result',
                    label: t('activity_log.result'),
                    value: state.result,
                    options: [
                        { value: 'success', label: t('activity_log.result_success') },
                        { value: 'blocked', label: t('activity_log.result_blocked') },
                    ],
                },
            ]"
            @change="set"
        />

        <!-- Rentang tanggal berdiri di luar `FilterBar`: komponen itu menggambar
             dropdown, dan rentang butuh DUA kontrol tanggal yang saling terkait.
             Memaksanya masuk ke sana berarti menambah bentuk ketiga ke komponen
             yang dipakai delapan layar lain. -->
        <div class="flex flex-wrap items-center gap-2">
            <label for="log-from" class="text-body-s text-cool-70">{{ t('activity_log.date_range') }}</label>
            <input
                id="log-from"
                type="date"
                :value="state.from"
                :aria-label="t('activity_log.date_from')"
                class="h-10 border-b border-cool-30 bg-cool-10 px-3 text-body-s text-cool-90"
                @change="set('from', ($event.target as HTMLInputElement).value)"
            />
            <span class="text-body-s text-cool-60">–</span>
            <input
                type="date"
                :value="state.to"
                :aria-label="t('activity_log.date_to')"
                class="h-10 border-b border-cool-30 bg-cool-10 px-3 text-body-s text-cool-90"
                @change="set('to', ($event.target as HTMLInputElement).value)"
            />
        </div>

        <!-- Catatan `528:11530`. Ia menjelaskan kenapa layar ini tidak punya
             satu pun tombol yang mengubah isinya. -->
        <ContextNote>{{ t('activity_log.context_note') }}</ContextNote>

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="entries.data"
                row-key="id"
                :empty-message="t('activity_log.empty')"
            >
                <template #cell.at="{ row }">
                    <span class="text-body-s whitespace-nowrap text-cool-70">
                        {{ formatDateTime(row.at) }}
                    </span>
                </template>

                <template #cell.causer="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ row.causer }}</span>
                        <span v-if="row.causerEmail" class="text-body-xs text-cool-60">
                            {{ row.causerEmail }}
                        </span>
                    </span>
                </template>

                <!-- Nama dulu, jenis dan id di bawahnya: yang dicari orang
                     "siapa/apa yang diubah", bukan nama kelasnya. -->
                <!-- IP dan perangkat: "admin masuk" tidak bisa dibedakan dari
                     "seseorang dengan sandi admin masuk dari tempat lain"
                     tanpa keduanya. -->
                <template #cell.origin="{ row }">
                    <span v-if="!row.ip && !row.device" class="text-body-xs text-cool-60">
                        {{ t('activity_log.unknown_origin') }}
                    </span>
                    <span v-else class="flex flex-col">
                        <span
                            v-if="row.device"
                            class="text-body-s whitespace-nowrap text-cool-90"
                            :title="row.userAgent ?? undefined"
                        >
                            {{ row.device }}
                        </span>
                        <span v-if="row.ip" class="text-body-xs text-cool-60 tabular-nums">
                            {{ row.ip }}
                        </span>
                    </span>
                </template>

                <template #cell.event="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ row.eventLabel }}</span>
                        <span class="text-body-xs text-cool-60">{{ row.moduleLabel }}</span>
                    </span>
                </template>

                <template #cell.what="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-90">{{ row.subject }}</span>
                        <!-- Nama jenisnya TIDAK diulang: `moduleLabel` sudah
                             mengatakannya ("News Category"), jadi menambahkan
                             "NewsCategory #6" cuma mencetak hal yang sama dua
                             kali. Yang disisakan id-nya, supaya baris ini tetap
                             bisa ditunjuk saat menelusuri. -->
                        <span v-if="row.subjectId" class="text-body-xs text-cool-60">
                            #{{ row.subjectId }}
                        </span>
                    </span>
                </template>

                <!-- Warna SELALU berpasangan dengan katanya. "Blocked" yang
                     hanya dibedakan warna hilang untuk pembaca yang tidak
                     membedakannya, dan ini kolom yang justru dicari saat
                     seseorang menyelidiki. -->
                <template #cell.result="{ row }">
                    <span
                        class="inline-flex border px-2 py-1 text-body-xs"
                        :class="
                            row.result === 'blocked'
                                ? 'border-danger bg-transparent text-danger'
                                : 'border-transparent bg-cool-10 text-cool-90'
                        "
                    >
                        {{ t(`activity_log.result_${row.result}`) }}
                    </span>
                </template>

                <!-- Diff dilipat: satu penyuntingan bisa mengubah belasan
                     atribut, dan membentangkan semuanya membuat tabelnya
                     mustahil dipindai. -->
                <template #cell.changes="{ row }">
                    <span v-if="row.changes.length === 0" class="text-body-xs text-cool-60">
                        {{ t('activity_log.no_changes') }}
                    </span>

                    <details v-else class="group">
                        <summary
                            class="cursor-pointer list-none text-body-xs text-cool-70 underline underline-offset-2"
                        >
                            {{ t('activity_log.show_changes', { count: row.changes.length }) }}
                        </summary>

                        <ul class="mt-2 flex flex-col gap-1">
                            <li
                                v-for="change in row.changes"
                                :key="change.field"
                                class="text-body-xs text-cool-70"
                            >
                                <code class="text-editor-80">{{ change.field }}</code>
                                <span class="text-cool-40"> · </span>
                                <span class="line-through">{{ change.from ?? '—' }}</span>
                                <span class="text-cool-40"> → </span>
                                <span class="text-cool-90">{{ change.to ?? '—' }}</span>
                            </li>
                        </ul>
                    </details>
                </template>
            </DataTable>

            <AppPagination
                :current-page="entries.current_page"
                :last-page="entries.last_page"
                :href-for="(n) => `/activity-log?page=${n}`"
                @navigate="go($event)"
            />
        </div>
    </AdminLayout>
</template>
