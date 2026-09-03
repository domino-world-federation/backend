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
import ContextNote from '@/Components/ContextNote.vue'
import { useIndexFilters } from '@/composables/useIndexFilters'
import { formatDateTime } from '@/utils/format'
import type { Paginated, TableColumn } from '@/types'

interface Row {
    id: number
    name: string
    email: string
    roles: string[]
    federation: string | null
    mfaStatus: string
    twoFactorEnabled: boolean
    isActive: boolean
    isSelf: boolean
    createdAt: string | null
    lastLoginAt: string | null
    /** `null` begitu akunnya pernah dipakai — lihat controller. */
    invitationState: string | null
}

const props = defineProps<{
    users: Paginated<Row>
    roles: Array<{ value: string; label: string; scope: string }>
    filters: { q: string; role: string; status: string; mfa: string }
}>()

const { t } = useI18n()

const { state, set, go, loading } = useIndexFilters('/users', {
    q: props.filters.q,
    role: props.filters.role,
    status: props.filters.status,
    mfa: props.filters.mfa,
})

/*
 * Kolomnya persis `528:8821`, dan kolom terakhir sebelum menu adalah
 * "Last Login" — BUKAN "Last Modified" seperti daftar isi lainnya.
 *
 * Bedanya bukan kosmetik. Untuk sebuah artikel, pertanyaan yang berguna adalah
 * "siapa terakhir menyuntingnya"; untuk sebuah akun admin, pertanyaannya
 * "kapan terakhir dipakai" — itu yang menjawab apakah akun ini masih hidup dan
 * mana yang layak dicabut.
 */
const columns: TableColumn[] = [
    { key: 'admin', label: t('users.admin') },
    { key: 'roles', label: t('users.roles') },
    { key: 'mfa', label: t('users.mfa_status') },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'created', label: t('news.created'), width: '130px' },
    { key: 'lastLogin', label: t('users.last_login'), width: '190px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function setStatus(row: Row, value: boolean): void {
    router.patch(`/users/${row.id}/status`, { is_active: value }, {
        preserveScroll: true,
        preserveState: true,
    })
}

function resendInvitation(row: Row): void {
    router.post(`/users/${row.id}/invitation/resend`, {}, { preserveScroll: true })
}

function revokeInvitation(row: Row): void {
    router.delete(`/users/${row.id}/invitation`, { preserveScroll: true })
}

function exportHref(): string {
    const query = new URLSearchParams(
        Object.entries(state.value).filter(([, v]) => v !== ''),
    ).toString()

    return query === '' ? '/users/export' : `/users/export?${query}`
}

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/users/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('users.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('users.title')"
            :breadcrumbs="[{ label: t('users.group') }, { label: t('users.title') }]"
        >
            <template #actions>
                <AppButton :href="exportHref()" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
                <AppButton href="/users/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('users.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <FilterBar
            v-model:search="state.q"
            :search-placeholder="t('users.search_placeholder')"
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
                    key: 'role',
                    label: t('users.roles'),
                    value: state.role,
                    options: roles.map((r) => ({ value: r.value, label: r.label })),
                },
                {
                    key: 'mfa',
                    label: t('users.mfa_filter'),
                    value: state.mfa,
                    options: [
                        { value: 'enrolled', label: t('users.mfa_enrolled') },
                        { value: 'setup_required', label: t('users.mfa_setup_required') },
                    ],
                },
            ]"
            @change="set"
        />

        <!-- Catatan `528:9743`. Bagian keduanya ("MFA is mandatory … not
             configurable per account") TIDAK berlaku di implementasi ini: 2FA
             tetap diatur per akun atas keputusan pemilik repo, dan kalimatnya
             disesuaikan supaya layar tidak menjanjikan hal yang tidak
             ditegakkannya. Penyimpangannya dicatat di docs/PROGRESS.md. -->
        <ContextNote>{{ t('users.context_note') }}</ContextNote>

        <div class="flex flex-col items-center gap-4">
            <SkeletonTable v-if="loading" :columns="columns.length" />

            <DataTable
                v-else
                :columns="columns"
                :rows="users.data"
                row-key="id"
                :empty-message="t('users.empty')"
            >
                <!-- Nama di atas, email di bawahnya — `528:8854`. Satu sel,
                     karena keduanya mengidentifikasi orang yang sama dan kolom
                     terpisah untuk email akan menggandakan lebar tabelnya. -->
                <template #cell.admin="{ row }">
                    <span class="flex flex-col">
                        <span class="flex items-center gap-2 text-body-s text-cool-90">
                            {{ row.name }}
                            <span
                                v-if="row.isSelf"
                                class="border border-cool-30 px-1.5 py-0.5 text-body-xs text-cool-60"
                            >
                                {{ t('users.you') }}
                            </span>
                        </span>
                        <span class="text-body-xs text-cool-60">{{ row.email }}</span>
                    </span>
                </template>

                <template #cell.roles="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-70">{{ row.roles.join(', ') }}</span>
                        <!-- Lingkup federasi dicetak di bawah perannya, bukan
                             sebagai kolom sendiri: ia hanya ada untuk sebagian
                             baris, dan kolom yang mayoritas selnya kosong
                             memakan lebar tanpa memberi apa pun. -->
                        <span v-if="row.federation" class="text-body-xs text-cool-60">
                            {{ row.federation }}
                        </span>
                    </span>
                </template>

                <template #cell.mfa="{ row }">
                    <span
                        class="inline-flex border px-2 py-1 text-body-xs"
                        :class="
                            row.mfaStatus === 'enrolled'
                                ? 'border-transparent bg-cool-10 text-cool-90'
                                : 'border-primary-60 bg-transparent text-cool-90'
                        "
                    >
                        {{ t(`users.mfa_${row.mfaStatus}`) }}
                    </span>
                </template>

                <template #cell.status="{ row }">
                    <AppToggle
                        :model-value="row.isActive"
                        :label="`${t('common.status')} ${row.name}`"
                        hide-label
                        :disabled="row.isSelf"
                        @update:model-value="setStatus(row as Row, $event)"
                    />
                </template>

                <template #cell.created="{ row }">
                    <span class="text-body-s text-cool-70">{{ formatDateTime(row.createdAt) }}</span>
                </template>

                <!-- "First login pending" (`528:8909`) untuk yang belum pernah
                     masuk. Keadaan undangannya dicetak di bawahnya selama akun
                     itu masih menunggu — di situlah orang mencarinya saat
                     bertanya "kenapa dia belum bisa masuk". -->
                <template #cell.lastLogin="{ row }">
                    <span class="flex flex-col">
                        <span class="text-body-s text-cool-70">
                            {{
                                row.lastLoginAt
                                    ? formatDateTime(row.lastLoginAt)
                                    : t('users.first_login_pending')
                            }}
                        </span>
                        <span v-if="row.invitationState" class="text-body-xs text-cool-60">
                            {{ t(`invitation.state_${row.invitationState}`) }}
                        </span>
                    </span>
                </template>

                <template #cell.actions="{ row }">
                    <RowMenu :label="t('common.row_actions', { name: row.name })">
                        <Link
                            :href="`/users/${row.id}/edit`"
                            class="block px-4 py-2 text-body-s text-cool-90 hover:bg-cool-10"
                        >
                            {{ t('common.edit') }}
                        </Link>

                        <!-- Resend dan Revoke hanya muncul selama undangannya
                             belum diterima — "can be resent or revoked from
                             Admin Users" (`529:9716`). Sesudah diterima
                             keduanya tidak punya sasaran, dan tombol yang
                             berujung galat cuma mengajari orang mengabaikannya. -->
                        <template v-if="row.invitationState !== null">
                            <button
                                type="button"
                                class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-cool-90 hover:bg-cool-10"
                                @click="resendInvitation(row as Row)"
                            >
                                {{ t('invitation.resend') }}
                            </button>
                            <button
                                v-if="row.invitationState === 'pending'"
                                type="button"
                                class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-cool-90 hover:bg-cool-10"
                                @click="revokeInvitation(row as Row)"
                            >
                                {{ t('invitation.revoke') }}
                            </button>
                        </template>

                        <button
                            v-if="!row.isSelf"
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
                :current-page="users.current_page"
                :last-page="users.last_page"
                :href-for="(n) => `/users?page=${n}`"
                @navigate="go($event)"
            />
        </div>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('users.delete_title')"
            :description="t('users.delete_body', { name: removing?.name ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
