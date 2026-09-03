<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import SelectField from '@/Components/SelectField.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

const props = defineProps<{
    federation: {
        id: number
        name: string
        country: string
        flagUrl: string | null
        tier: string | null
        joinedYear: number | null
        president: string | null
        headquarters: string | null
        email: string | null
        phone: string | null
        websiteUrl: string | null
        isActive: boolean
    } | null
    tiers: Array<{ value: string; label: string }>
}>()

const { t } = useI18n()

const isEdit = props.federation !== null
const title = computed(() => (isEdit ? t('federations.edit') : t('federations.add')))

const form = useForm({
    name: props.federation?.name ?? '',
    country: props.federation?.country ?? '',
    flag: null as File | null,
    tier: props.federation?.tier ?? null,
    joined_year: props.federation?.joinedYear ?? '',
    president: props.federation?.president ?? '',
    headquarters: props.federation?.headquarters ?? '',
    email: props.federation?.email ?? '',
    phone: props.federation?.phone ?? '',
    website_url: props.federation?.websiteUrl ?? '',
    is_active: props.federation?.isActive ?? true,
})

function submit(): void {
    const options = { forceFormData: true }

    if (isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/federations/${props.federation!.id}`,
            options,
        )
        return
    }

    form.post('/federations', options)
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('federations.title') },
                { label: t('federations.list'), href: '/federations' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('federations.data')">
                <FormRow :label="t('federations.name')" :description="t('federations.name_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.name" :error="form.errors.name" autofocus />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.country')" :description="t('federations.country_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.country" :error="form.errors.country" />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.flag')" :description="t('federations.flag_hint')">
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.flag"
                            kind="image"
                            :existing-url="federation?.flagUrl"
                            :error="form.errors.flag"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.tier')" :description="t('federations.tier_hint')">
                    <template #default="{ id }">
                        <SelectField :id="id" v-model="form.tier" :options="tiers" :error="form.errors.tier" />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.joined_year')" :description="t('federations.joined_year_hint')">
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.joined_year"
                            type="number"
                            min="1900"
                            placeholder="2025"
                            :error="form.errors.joined_year"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.president')" :description="t('federations.president_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.president" :error="form.errors.president" />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.headquarters')" :description="t('federations.headquarters_hint')">
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.headquarters"
                            placeholder="Miami, FL, United States"
                            :error="form.errors.headquarters"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.email')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.email" type="email" :error="form.errors.email" />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.phone')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.phone" :error="form.errors.phone" />
                    </template>
                </FormRow>

                <FormRow :label="t('federations.website')" :description="t('federations.website_hint')">
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.website_url"
                            type="url"
                            placeholder="https://"
                            :error="form.errors.website_url"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('common.status')" :description="t('federations.status_hint')" compact>
                    <div class="flex flex-col gap-2">
                        <AppToggle v-model="form.is_active" :label="t('common.active')" />
                        <p v-if="form.errors.is_active" role="alert" class="text-body-xs text-danger">
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                </FormRow>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/federations" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
