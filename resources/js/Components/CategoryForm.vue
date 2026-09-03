<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import type { Crumb } from '@/types'

/** Formulir kategori — dipakai News dan FAQ. Lihat catatan di CategoryIndex. */
const props = defineProps<{
    title: string
    breadcrumbs: Crumb[]
    baseUrl: string
    statusHint: string
    category: { id: number; name: string; isActive: boolean } | null
}>()

const { t } = useI18n()

const isEdit = props.category !== null

const form = useForm({
    name: props.category?.name ?? '',
    is_active: props.category?.isActive ?? true,
})

function submit(): void {
    if (isEdit) {
        form.put(`${props.baseUrl}/${props.category!.id}`)
        return
    }
    form.post(props.baseUrl)
}
</script>

<template>
    <PageHeader :title="title" :breadcrumbs="breadcrumbs" />

    <form class="flex flex-col items-end gap-6" @submit.prevent="submit">
        <CardSection :title="t('category.data')">
            <FormRow
                :label="t('category.name')"
                :description="t('category.name_hint')"
                required
            >
                <template #default="{ id }">
                    <AppField
                        :id="id"
                        v-model="form.name"
                        
                        :error="form.errors.name"
                        autofocus
                    />
                </template>
            </FormRow>

            <FormRow :label="t('common.status')" :description="statusHint" compact>
                <AppToggle v-model="form.is_active" :label="t('common.active')" />
            </FormRow>
        </CardSection>

        <div class="flex items-center gap-2">
            <AppButton :href="baseUrl" variant="outline">{{ t('common.cancel') }}</AppButton>
            <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
        </div>

        <UnsavedGuard :dirty="form.isDirty" />
    </form>
</template>
