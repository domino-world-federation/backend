<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import SelectField from '@/Components/SelectField.vue'
import ContextNote from '@/Components/ContextNote.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import RichTextEditor from '@/Components/Editor/RichTextEditor.vue'

const props = defineProps<{
    faq: {
        id: number
        question: string
        answer: string
        categoryId: number
        /** Halaman tempat FAQ ini menempel — DIBACA saja di layar ini. */
        pages: string[]
        isActive: boolean
    } | null
    categories: Array<{ value: number; label: string }>
}>()

const { t } = useI18n()

const isEdit = props.faq !== null
const title = computed(() => (isEdit ? t('faq.edit') : t('faq.add')))

/** Sudah berupa label ("Home Page"), bukan kunci — server yang menerjemahkan. */
const appliedTo = computed(() => props.faq?.pages ?? [])

const form = useForm({
    question: props.faq?.question ?? '',
    answer: props.faq?.answer ?? '',
    faq_category_id: props.faq?.categoryId ?? null,
    is_active: props.faq?.isActive ?? true,
})

function submit(): void {
    if (isEdit) {
        form.put(`/faq/${props.faq!.id}`)
        return
    }
    form.post('/faq')
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('faq.title'), href: '/faq' },
                { label: t('faq.list'), href: '/faq' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit">
            <CardSection :title="t('faq.data')">
                <FormRow
                    :label="t('faq.question')"
                    :description="t('faq.question_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.question"
                            :placeholder="t('faq.question_placeholder')"
                            :error="form.errors.question"
                            autofocus
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('faq.answer')"
                    :description="t('faq.answer_hint')"
                    required
                >
                    <template #default="{ id }">
                        <RichTextEditor :id="id" v-model="form.answer" :error="form.errors.answer" />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('common.category')"
                    :description="t('faq.category_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.faq_category_id"
                            :options="categories"
                            :placeholder="t('gallery.select_event')"
                            :error="form.errors.faq_category_id"
                        />
                    </template>
                </FormRow>

                <!-- DIBACA saja. Halaman tempat pertanyaan ini tampil disetel
                     di "FAQ per Halaman", bukan di sini — sejak penempatan
                     punya peringkatnya sendiri, sederet centang tidak lagi bisa
                     mengatakan nomor berapa, dan yang bisa mengatakannya cuma
                     layar yang memperlihatkan halamannya utuh. -->
                <FormRow :label="t('faq.apply_to_page')" compact>
                    <div class="flex flex-col gap-3">
                        <span class="text-body-s text-cool-90">
                            {{
                                appliedTo.length > 0
                                    ? appliedTo.join(', ')
                                    : t('faq.apply_to_page_none')
                            }}
                        </span>

                        <ContextNote>
                            {{ t('faq.apply_to_page_moved') }}
                            <AppButton href="/faq/pages" variant="link" size="s">
                                {{ t('faq.pages_link') }}
                            </AppButton>
                        </ContextNote>
                    </div>
                </FormRow>

                <FormRow
                    :label="t('common.status')"
                    :description="t('faq.status_hint')"
                    compact
                >
                    <AppToggle v-model="form.is_active" :label="t('common.active')" />
                </FormRow>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/faq" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
