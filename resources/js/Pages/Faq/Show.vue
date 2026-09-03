<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhArrowLeft, PhPencilSimple } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppButton from '@/Components/AppButton.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import RecordMeta from '@/Components/RecordMeta.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Layar baca satu FAQ — dibuka dari pertanyaannya di daftar.
 *
 * Bentuknya sengaja sama persis dengan `News/Articles/Show.vue`: kartu identitas
 * di atas (status, judul, meta), lalu isinya. Dua modul yang menjawab pertanyaan
 * yang sama harus terlihat sama; kalau tidak, orang harus belajar dua kali.
 */
const props = defineProps<{
    faq: {
        id: number
        question: string
        answer: string | null
        category: string | null
        pages: string[]
        isActive: boolean
        createdAt: string | null
        updatedAt: string | null
        updatedBy: string | null
    }
}>()

const { t } = useI18n()

const removing = ref(false)
const processing = ref(false)

function destroy(): void {
    processing.value = true
    router.delete(`/faq/${props.faq.id}`, {
        onFinish: () => {
            processing.value = false
            removing.value = false
        },
    })
}
</script>

<template>
    <Head :title="faq.question" />

    <AdminLayout>
        <PageHeader
            :title="t('faq.detail')"
            :breadcrumbs="[
                { label: t('faq.title'), href: '/faq' },
                { label: t('faq.list'), href: '/faq' },
                { label: faq.question },
            ]"
        >
            <template #actions>
                <AppButton href="/faq" variant="outline">
                    <template #iconLeft><PhArrowLeft :size="24" /></template>
                    {{ t('news.back_to_list') }}
                </AppButton>
                <AppButton :href="`/faq/${faq.id}/edit`">
                    <template #iconLeft><PhPencilSimple :size="24" /></template>
                    {{ t('common.edit') }}
                </AppButton>
            </template>
        </PageHeader>

        <CardSection>
            <div class="flex flex-col gap-3">
                <span
                    class="w-fit border px-2 py-1 text-body-xs"
                    :class="
                        faq.isActive
                            ? 'border-transparent bg-cool-10 text-cool-90'
                            : 'border-cool-30 text-cool-60'
                    "
                >
                    {{ faq.isActive ? t('common.active') : t('common.inactive') }}
                </span>

                <h1 class="text-heading-4 text-cool-100">{{ faq.question }}</h1>
            </div>

            <RecordMeta
                :items="[
                    { label: t('common.category'), value: faq.category },
                    { label: t('faq.applied_to'), value: faq.pages.join(', ') },
                    { label: t('news.created'), at: faq.createdAt },
                    { label: t('news.last_modified'), value: faq.updatedBy, at: faq.updatedAt },
                ]"
            />
        </CardSection>

        <CardSection :title="t('faq.answer')">
            <p v-if="!faq.answer" class="text-body-s text-cool-60">{{ t('news.no_content') }}</p>

            <!-- Aman: jawabannya sudah dilewatkan `Purifier::clean()` saat
                 disimpan (lihat `FaqController::payload`). -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-else class="prose-dwf" v-html="faq.answer" />
        </CardSection>

        <div class="flex justify-end">
            <AppButton variant="outline" @click="removing = true">{{ t('common.delete') }}</AppButton>
        </div>

        <ConfirmDialog
            :open="removing"
            variant="deletion"
            :title="t('faq.delete_title')"
            :description="t('faq.delete_body', { name: faq.question })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = false"
        />
    </AdminLayout>
</template>
