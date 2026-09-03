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
import ContextNote from '@/Components/ContextNote.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    ipRange: string
    scope: string
    scopeLabel: string
    validity: string
    expiresAt: string | null
    isExpired: boolean
    isActive: boolean
    updatedBy: string | null
    updatedAt: string | null
}

const props = defineProps<{
    rules: Paginated<Row>
    roles: Array<{ value: number; label: string }>
    filters: { q: string; status: string; scope: string; validity: string }
    currentIp: string | null
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/ip-whitelist', {
    q: props.filters.q,
    status: props.filters.status,
    scope: props.filters.scope,
    validity: props.filters.validity,
})

// Lebar kolom dari `527:7666` dan saudara-saudaranya: nama mengisi sisa,
// alamat 200, validity 130, status 100, "siapa + kapan" 270.
const columns: TableColumn[] = [
    { key: 'name', label: t('ip_whitelist.name') },
    { key: 'ipRange', label: t('ip_whitelist.ip'), width: '200px' },
    { key: 'scope', label: t('ip_whitelist.scope') },
    { key: 'validity', label: t('ip_whitelist.validity'), width: '130px' },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'updated', label: t('ip_whitelist.last_updated'), width: '270px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

/**
 * Sakelar status disimpan seketika — tidak perlu membuka formulirnya.
 *
 * Server tetap memeriksa apakah mematikannya akan mengunci pemakainya sendiri;
 * kalau ya, ia membalas galat validasi dan sakelarnya kembali ke posisi semula
 * karena `preserveState` memuat ulang propsnya.
 */
function setStatus(row: Row, value: boolean): void {
    router.patch(`/ip-whitelist/${row.id}/status`, { is_active: value }, {
        preserveScroll: true,
        preserveState: true,
    })
}

/** Ekspor mengikuti filter yang sedang aktif. */
function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/ip-whitelist/export' : `/ip-whitelist/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/ip-whitelist/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('ip_whitelist.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('ip_whitelist.title')"
            :breadcrumbs="[{ label: t('users.group') }, { label: t('ip_whitelist.list') }]"
        >
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/ip-whitelist/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('ip_whitelist.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('ip_whitelist.search_placeholder')"
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
                    key: 'scope',
                    label: t('ip_whitelist.scope'),
                    value: state.scope,
                    options: [
                        { value: 'all_admins', label: t('ip_whitelist.scope_all') },
                        { value: 'role', label: t('ip_whitelist.scope_role') },
                        { value: 'user', label: t('ip_whitelist.scope_user') },
                    ],
                },
                {
                    key: 'validity',
                    label: t('ip_whitelist.validity'),
                    value: state.validity,
                    options: [
                        { value: 'permanent', label: t('ip_whitelist.permanent') },
                        { value: 'temporary', label: t('ip_whitelist.temporary') },
                    ],
                },
            ]"
            @change="set"
        />

        <!-- Pita catatan `527:7869`. Ia bukan hiasan: ia satu-satunya tempat
             layar ini mengatakan bahwa yang dilihat orang punya akibat pada
             siapa yang bisa masuk. -->
        <ContextNote tone="warning">{{ t('ip_whitelist.security_note') }}</ContextNote>

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="rules.data"
                row-key="id"
                :empty-message="rules.total === 0 ? t('ip_whitelist.empty_all') : t('ip_whitelist.empty')"
            >
                <template #cell.name="{ row }">
                    <Link
                        :href="`/ip-whitelist/${row.id}/edit`"
                        class="block max-w-[280px] text-body-s text-cool-100 underline decoration-cool-30 underline-offset-4 transition-colors hover:text-primary-90"
                    >
                        {{ row.name }}
                    </Link>
                </template>

                <!-- Monospace: alamat IP dibaca karakter per karakter, dan
                     `1`/`l` serta `0`/`O` di font proporsional adalah persis
                     jenis salah baca yang membuat orang menyalahkan aturannya. -->
                <template #cell.ipRange="{ row }">
                    <span class="font-mono text-body-s text-cool-90">{{ row.ipRange }}</span>
                </template>

                <template #cell.scope="{ row }">
                    <span class="text-body-s text-cool-70">{{ row.scopeLabel }}</span>
                </template>

                <template #cell.validity="{ row }">
                    <div class="flex flex-col">
                        <span class="text-body-s text-cool-70">
                            {{
                                row.validity === 'permanent'
                                    ? t('ip_whitelist.permanent')
                                    : formatDateTime(row.expiresAt)
                            }}
                        </span>
                        <!-- Kedaluwarsa ditulis terpisah dari Status karena
                             keduanya memang beda pertanyaan: aturan ini bisa
                             menyala (Active) dan sudah lewat tanggalnya
                             sekaligus. Yang menegakkan adalah `enforceable()`,
                             dan tanpa penanda ini barisnya terlihat berlaku. -->
                        <span v-if="row.isExpired" class="text-body-xs text-danger">
                            {{ t('ip_whitelist.expired') }}
                        </span>
                    </div>
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
                            :href="`/ip-whitelist/${row.id}/edit`"
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

            <!-- Alamat sendiri dicetak DI SINI, bukan cuma di formulir: sakelar
                 status ada di layar ini, dan ia cara tercepat mengusir diri
                 sendiri. Orang perlu tahu alamat mana yang harus tetap tercakup
                 sebelum menekannya. -->
            <p v-if="currentIp" class="self-start text-body-xs text-cool-60">
                {{ t('ip_whitelist.current_ip', { ip: currentIp }) }}
            </p>

            <AppPagination
                :current-page="rules.current_page"
                :last-page="rules.last_page"
                :href-for="(n) => `/ip-whitelist?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('ip_whitelist.delete_title')"
            :description="t('ip_whitelist.delete_body', { name: removing?.name ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
