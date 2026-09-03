<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { PhImageSquare, PhPlus } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppButton from '@/Components/AppButton.vue'
import DataTable from '@/Components/DataTable.vue'
import RowMenu from '@/Components/RowMenu.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import ContextNote from '@/Components/ContextNote.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useI18n } from '@/composables/useI18n'
import type { TableColumn } from '@/types'

interface Row {
    id: number
    route: string
    label: string
    title: string | null
    description: string | null
    ogImageUrl: string | null
    isDefault: boolean
    updatedAt: string | null
    updatedBy: string | null
}

defineProps<{ fallback: Row | null; pages: Row[] }>()

const { t } = useI18n()

const columns: TableColumn[] = [
    { key: 'page', label: t('seo.label') },
    { key: 'meta', label: t('seo.meta_title') },
    { key: 'image', label: t('seo.og_image'), width: '150px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/seo-social/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('seo.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('seo.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('seo.title') }]"
        >
            <template #description>{{ t('seo.hint') }}</template>
            <template #actions>
                <AppButton href="/seo-social/create">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('seo.add') }}
                </AppButton>
            </template>
        </PageHeader>

        <!-- Bawaan situs berdiri SENDIRI di atas tabel. Menaruhnya sebagai baris
             di tengah daftar membuatnya terbaca seperti halaman bernama "*",
             padahal ia cadangan untuk seluruh halaman. -->
        <CardSection v-if="fallback" :title="t('seo.fallback_title')">
            <template #header>
                <AppButton :href="`/seo-social/${fallback.id}/edit`" variant="link" size="s">
                    {{ t('common.edit') }}
                </AppButton>
            </template>

            <ContextNote tone="warning">{{ t('seo.fallback_hint') }}</ContextNote>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:gap-6">
                <span class="flex h-20 w-36 shrink-0 items-center justify-center overflow-hidden bg-cool-10">
                    <img
                        v-if="fallback.ogImageUrl"
                        :src="fallback.ogImageUrl"
                        :alt="fallback.label"
                        class="size-full object-cover"
                    />
                    <PhImageSquare v-else :size="24" class="text-cool-40" aria-hidden="true" />
                </span>

                <span class="flex min-w-0 flex-col gap-1">
                    <span class="text-body-s text-cool-90">{{ fallback.title ?? t('common.none') }}</span>
                    <span class="text-body-xs text-cool-60">{{ fallback.description ?? t('common.none') }}</span>
                </span>
            </div>
        </CardSection>

        <DataTable :columns="columns" :rows="pages" row-key="id" :empty-message="t('seo.empty')">
            <template #cell.page="{ row }">
                <span class="flex flex-col">
                    <span class="text-body-s text-cool-90">{{ row.label }}</span>
                    <span class="font-mono text-body-xs text-cool-60">{{ row.route }}</span>
                </span>
            </template>

            <!-- Field yang kosong tidak dicetak sebagai "—": ia MEWARISI bawaan
                 situs, dan itu keadaan yang berbeda dari "tidak ada". -->
            <template #cell.meta="{ row }">
                <span class="flex flex-col">
                    <span class="text-body-s text-cool-90">
                        {{ row.title ?? t('seo.inherits') }}
                    </span>
                    <span class="line-clamp-2 text-body-xs text-cool-60">
                        {{ row.description ?? t('seo.inherits') }}
                    </span>
                </span>
            </template>

            <template #cell.image="{ row }">
                <span class="flex h-12 w-24 items-center justify-center overflow-hidden bg-cool-10">
                    <img
                        v-if="row.ogImageUrl"
                        :src="row.ogImageUrl"
                        :alt="row.label"
                        class="size-full object-cover"
                    />
                    <PhImageSquare v-else :size="18" class="text-cool-40" aria-hidden="true" />
                </span>
            </template>

            <template #cell.updated="{ row }">
                <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
            </template>

            <template #cell.actions="{ row }">
                <RowMenu :label="t('common.row_actions', { name: row.label })">
                    <Link
                        :href="`/seo-social/${row.id}/edit`"
                        class="block w-full px-4 py-2 text-left text-body-s text-cool-90 hover:bg-cool-10"
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

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('seo.delete_title')"
            :description="t('seo.delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
