<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'

const props = defineProps<{ settings: Record<string, string> }>()

const { t } = useI18n()

const form = useForm({ ...props.settings })

const SOCIAL = [
    { key: 'social_instagram', label: 'Instagram', placeholder: '{instagram_username}' },
    { key: 'social_tiktok', label: 'TikTok', placeholder: '{tiktok_username}' },
    { key: 'social_x', label: 'X', placeholder: '{x_username}' },
    { key: 'social_facebook', label: 'Facebook', placeholder: '{facebook_username}' },
    { key: 'social_youtube', label: 'YouTube', placeholder: '{youtube_username}' },
]
</script>

<template>
    <Head :title="t('settings.contact_social')" />

    <AdminLayout>
        <PageHeader
            :title="t('settings.contact_social')"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('settings.contact_social') },
            ]"
        />

        <form class="flex flex-col items-end gap-6" @submit.prevent="form.put('/contact-social')">
            <CardSection :title="t('settings.contact_information')">
                <FormRow
                    :label="t('settings.primary_email')"
                    :description="t('settings.primary_email_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.primary_email"
                            type="email"
                            placeholder="contact@dwf-domino.org"
                            :error="form.errors.primary_email"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('settings.footer_address')"
                    :description="t('settings.footer_address_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.footer_address_label"
                            placeholder="Headquarters, Lausanne, CH"
                            :error="form.errors.footer_address_label"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('settings.hq_address')"
                    :description="t('settings.hq_address_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.headquarters_address"
                            textarea
                            placeholder="Maison du Sport International, Lausanne, Switzerland"
                            :error="form.errors.headquarters_address"
                        />
                    </template>
                </FormRow>
            </CardSection>

            <CardSection :title="t('settings.social_media')">
                <FormRow
                    v-for="social in SOCIAL"
                    :key="social.key"
                    :label="social.label"
                    :description="t('settings.social_hint')"
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form[social.key]"
                            :placeholder="social.placeholder"
                            :error="(form.errors as Record<string, string>)[social.key]"
                        />
                    </template>
                </FormRow>
            </CardSection>

            <CardSection :title="t('settings.form_settings')">
                <FormRow
                    :label="t('settings.recipient')"
                    :description="t('settings.recipient_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.form_recipient_email"
                            type="email"
                            placeholder="contact@dwf-domino.org"
                            :error="form.errors.form_recipient_email"
                        />
                    </template>
                </FormRow>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/dashboard" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
