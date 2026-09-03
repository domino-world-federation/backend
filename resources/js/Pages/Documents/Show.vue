<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhArrowLeft, PhDownloadSimple, PhFilePdf, PhPencilSimple } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppButton from '@/Components/AppButton.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RecordMeta from '@/Components/RecordMeta.vue'
import StatusPill from '@/Components/StatusPill.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Layar baca satu dokumen press release.
 *
 * Isinya PDF, jadi yang bisa ditampilkan cuma keterangannya — dan justru itu
 * sebabnya layar ini berguna: memeriksa "kategorinya benar tidak, berkasnya yang
 * mana" tidak seharusnya menuntut membuka formulir yang bisa menimpa berkasnya.
 */
const props = defineProps<{
    document: {
        id: number
        title: string
        slug: string
        category: string | null
        visibility: string
        fileName: string
        fileSize: string
        fileUrl: string | null
        publishedAt: string | null
        publishedBy: string | null
        createdAt: string | null
        createdBy: string | null
        updatedAt: string | null
        updatedBy: string | null
    }
}>()

const { t } = useI18n()

const removing = ref(false)
const processing = ref(false)

function destroy(): void {
    processing.value = true
    router.delete(`/documents/${props.document.id}`, {
        onFinish: () => {
            processing.value = false
            removing.value = false
        },
    })
}
</script>

<template>
    <Head :title="document.title" />

    <AdminLayout>
        <PageHeader
            :title="t('documents.detail')"
            :breadcrumbs="[
                { label: t('documents.title'), href: '/documents' },
                { label: t('documents.list'), href: '/documents' },
                { label: document.title },
            ]"
        >
            <template #actions>
                <AppButton href="/documents" variant="outline">
                    <template #iconLeft><PhArrowLeft :size="24" /></template>
                    {{ t('news.back_to_list') }}
                </AppButton>
                <AppButton :href="`/documents/${document.id}/edit`">
                    <template #iconLeft><PhPencilSimple :size="24" /></template>
                    {{ t('common.edit') }}
                </AppButton>
            </template>
        </PageHeader>

        <CardSection>
            <div class="flex flex-col gap-3">
                <!-- Empat keadaan sekarang, bukan dua. Dipakai `StatusPill`
                     yang sama dengan News dan Gallery — satu kosakata untuk
                     satu konsep. -->
                <StatusPill class="w-fit" :value="document.visibility" />

                <h1 class="text-heading-4 text-cool-100">{{ document.title }}</h1>
            </div>

            <RecordMeta
                :items="[
                    { label: t('common.category'), value: document.category },
                    { label: t('news.field_slug'), value: document.slug },
                    { label: t('documents.size'), value: document.fileSize },
                    { label: t('gallery.published'), value: document.publishedBy, at: document.publishedAt },
                    { label: t('news.created'), value: document.createdBy, at: document.createdAt },
                    { label: t('news.last_modified'), value: document.updatedBy, at: document.updatedAt },
                ]"
            />
        </CardSection>

        <CardSection :title="t('documents.file')">
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex min-w-0 items-center gap-3">
                    <PhFilePdf :size="40" class="shrink-0 text-cool-60" aria-hidden="true" />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-body-s text-cool-90">{{ document.fileName }}</span>
                        <span class="text-body-xs text-cool-60">{{ document.fileSize }}</span>
                    </span>
                </span>

                <AppButton v-if="document.fileUrl" :href="document.fileUrl" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('documents.download') }}
                </AppButton>
            </div>
        </CardSection>

        <div class="flex justify-end">
            <AppButton variant="outline" @click="removing = true">{{ t('common.delete') }}</AppButton>
        </div>

        <ConfirmDialog
            :open="removing"
            variant="deletion"
            :title="t('documents.delete_title')"
            :description="t('documents.delete_body', { name: document.title })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = false"
        />
    </AdminLayout>
</template>
