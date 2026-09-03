<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ContextNote from '@/Components/ContextNote.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppButton from '@/Components/AppButton.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Tambah/ubah meta halaman — layarnya sendiri, bukan formulir yang terbuka di
 * atas daftarnya.
 *
 * Baris bawaan (`*`) memakai layar yang SAMA. Yang membedakannya cuma field
 * Route yang tidak digambar untuknya: mengubah `*` jadi path biasa akan
 * menghilangkan cadangan seluruh situs sekaligus, dan tidak ada satu layar pun
 * yang akan memberi tahu.
 */
const props = defineProps<{
    page: {
        id: number
        route: string
        label: string
        title: string | null
        description: string | null
        ogImageUrl: string | null
        isDefault: boolean
    } | null
}>()

const { t } = useI18n()

const isEdit = props.page !== null
const isFallback = props.page?.isDefault ?? false
const title = computed(() => (isEdit ? t('seo.edit') : t('seo.add')))

const form = useForm({
    route: props.page?.route ?? '',
    label: props.page?.label ?? '',
    title: props.page?.title ?? '',
    description: props.page?.description ?? '',
    og_image: null as File | null,
})

function submit(): void {
    // Keduanya POST: `PUT` tidak membawa berkas, dan gambar OG berkas.
    form.post(isEdit ? `/seo-social/${props.page!.id}` : '/seo-social', {
        forceFormData: true,
    })
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('seo.title'), href: '/seo-social' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('seo.data')">
                <ContextNote v-if="isFallback" tone="warning">
                    {{ t('seo.fallback_hint') }}
                </ContextNote>

                <FormRow
                    v-if="!isFallback"
                    :label="t('seo.route')"
                    :description="t('seo.route_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.route"
                            class="font-mono"
                            placeholder="/about"
                            :error="form.errors.route"
                            autofocus
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('seo.label')" :description="t('seo.label_hint')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.label"
                            :error="form.errors.label"
                            :autofocus="isFallback"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('seo.meta_title')" :description="t('seo.meta_title_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.title" :error="form.errors.title" />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('seo.meta_description')"
                    :description="t('seo.meta_description_hint')"
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.description"
                            textarea
                            :error="form.errors.description"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('seo.og_image')" :description="t('seo.og_image_hint')">
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.og_image"
                            kind="image"
                            :existing-url="page?.ogImageUrl"
                            :error="form.errors.og_image"
                        />
                    </template>
                </FormRow>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/seo-social" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
