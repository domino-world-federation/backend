<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { PhLock, PhPlus } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import DataTable from '@/Components/DataTable.vue'
import FilterBar from '@/Components/FilterBar.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RowMenu from '@/Components/RowMenu.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import ContextNote from '@/Components/ContextNote.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { useI18n } from '@/composables/useI18n'
import type { TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    type: string
    scope: string
    summary: string | null
    permissions: number
    users: number
    updatedBy: string | null
    updatedAt: string | null
    isSystem: boolean
    isSuperAdmin: boolean
}

const props = defineProps<{
    roles: Row[]
    filters: { q: string; type: string; scope: string }
}>()

const { t } = useI18n()

const { state, set } = useIndexFilters('/roles', {
    q: props.filters.q,
    type: props.filters.type,
    scope: props.filters.scope,
})

// Kolom persis `528:9745`. "Permission Summary" menggantikan hitungan izin
// sebagai kolom utama — angka "23" tidak memberi tahu siapa pun apa yang boleh
// dilakukan peran itu, sedangkan satu kalimat memberi tahu. Hitungannya tetap
// dicetak, tapi sebagai keterangan kecil di bawahnya.
const columns: TableColumn[] = [
    { key: 'name', label: t('roles.name') },
    { key: 'type', label: t('roles.type'), width: '110px' },
    { key: 'users', label: t('roles.admins'), width: '90px' },
    { key: 'scope', label: t('roles.scope'), width: '130px' },
    { key: 'summary', label: t('roles.summary') },
    { key: 'updated', label: t('roles.last_updated'), width: '230px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/roles/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('roles.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('roles.title')"
            :breadcrumbs="[{ label: t('users.group') }, { label: t('roles.title') }]"
        >
            <template #actions>
                <AppButton href="/roles/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('roles.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('roles.search_placeholder')"
            :filters="[
                {
                    key: 'type',
                    label: t('roles.type'),
                    value: state.type,
                    options: [
                        { value: 'system', label: t('roles.type_system') },
                        { value: 'custom', label: t('roles.type_custom') },
                    ],
                },
                {
                    key: 'scope',
                    label: t('roles.scope'),
                    value: state.scope,
                    options: [
                        { value: 'global', label: t('roles.scope_global') },
                        { value: 'federation', label: t('roles.scope_federation') },
                    ],
                },
            ]"
            @change="set"
        />

        <!-- Catatan `528:9744`. Ia menjelaskan dua hal yang tidak terlihat dari
             tabelnya: kenapa sebagian baris tidak punya tombol hapus, dan
             kenapa yang punya admin tidak bisa dihapus begitu saja. -->
        <ContextNote>{{ t('roles.context_note') }}</ContextNote>

        <DataTable :columns="columns" :rows="roles" row-key="id" :empty-message="t('roles.empty')">
            <template #cell.name="{ row }">
                <span class="flex items-center gap-2">
                    <span class="text-body-s text-cool-90">{{ row.name }}</span>
                    <PhLock
                        v-if="row.isSuperAdmin"
                        :size="14"
                        class="text-cool-40"
                        :aria-label="t('roles.super_admin_locked')"
                    />
                </span>
            </template>

            <template #cell.type="{ row }">
                <span
                    class="inline-flex border px-2 py-1 text-body-xs"
                    :class="
                        row.isSystem
                            ? 'border-transparent bg-cool-10 text-cool-90'
                            : 'border-cool-30 bg-transparent text-cool-70'
                    "
                >
                    {{ t(`roles.type_${row.type}`) }}
                </span>
            </template>

            <template #cell.users="{ row }">
                <span class="text-body-s text-cool-70">{{ row.users }}</span>
            </template>

            <template #cell.scope="{ row }">
                <span class="text-body-s text-cool-70">{{ t(`roles.scope_${row.scope}`) }}</span>
            </template>

            <!-- Ringkasan di atas, hitungan izin sebagai keterangan di bawahnya.
                 super-admin tidak punya baris izin; menampilkan "0" akan
                 terbaca seperti peran yang lumpuh, padahal justru sebaliknya. -->
            <template #cell.summary="{ row }">
                <span class="flex flex-col">
                    <span class="text-body-s text-cool-70">{{ row.summary ?? t('common.none') }}</span>
                    <span class="text-body-xs text-cool-60">
                        {{
                            row.isSuperAdmin
                                ? t('roles.super_admin_note')
                                : `${row.permissions} ${t('roles.permission_count').toLowerCase()}`
                        }}
                    </span>
                </span>
            </template>

            <template #cell.updated="{ row }">
                <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
            </template>

            <template #cell.actions="{ row }">
                <RowMenu v-if="!row.isSuperAdmin" :label="t('common.row_actions', { name: row.name })">
                    <Link
                        :href="`/roles/${row.id}/edit`"
                        class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                    >
                        {{ t('common.edit') }}
                    </Link>
                    <!-- Dua syarat, dan keduanya dari catatan di atas: peran
                         sistem dibangun ulang seeder, dan peran yang masih
                         dipakai harus dipindahkan dulu. -->
                    <button
                        v-if="!row.isSystem && row.users === 0"
                        type="button"
                        class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-danger hover:bg-cool-10"
                        @click="removing = row"
                    >
                        {{ t('common.delete') }}
                    </button>
                </RowMenu>
            </template>
        </DataTable>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('roles.delete_title')"
            :description="t('roles.delete_body', { name: removing?.name ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
