<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhArrowLeft, PhEnvelopeSimple } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppButton from '@/Components/AppButton.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { formatDateTime } from '@/utils/format'

const props = defineProps<{
    message: {
        id: number
        name: string
        email: string
        country: string | null
        topic: string | null
        subject: string
        body: string
        receivedAt: string | null
    }
}>()

const { t } = useI18n()

const removing = ref(false)
const processing = ref(false)

function destroy(): void {
    processing.value = true
    router.delete(`/contact-messages/${props.message.id}`, {
        onFinish: () => {
            processing.value = false
            removing.value = false
        },
    })
}

const FIELDS = computed(() => [
    { label: t('messages.full_name'), value: props.message.name },
    { label: t('messages.email_address'), value: props.message.email },
    { label: t('messages.country'), value: props.message.country ?? t('common.none') },
    { label: t('messages.topic'), value: props.message.topic ?? t('common.none') },
])
</script>

<template>
    <Head :title="message.subject" />

    <AdminLayout>
        <PageHeader
            :title="t('messages.detail')"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('messages.title'), href: '/contact-messages' },
                { label: message.subject },
            ]"
        >
            <template #actions>
                <AppButton href="/contact-messages" variant="outline">
                    <template #iconLeft><PhArrowLeft :size="24" /></template>
                    {{ t('messages.back') }}
                </AppButton>
                <AppButton :href="`mailto:${message.email}?subject=Re: ${message.subject}`" external>
                    <template #iconLeft><PhEnvelopeSimple :size="24" /></template>
                    {{ t('messages.contact_by_email') }}
                </AppButton>
            </template>
        </PageHeader>

        <CardSection :title="t('messages.sender_information')">
            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="field in FIELDS" :key="field.label" class="flex flex-col gap-1">
                    <dt class="text-body-xs text-cool-60">{{ field.label }}</dt>
                    <dd class="text-body-m text-cool-90">{{ field.value }}</dd>
                </div>
            </dl>
        </CardSection>

        <CardSection :title="t('messages.submission_details')">
            <div class="flex flex-col gap-1">
                <p class="text-body-xs text-cool-60">{{ t('messages.received') }}</p>
                <p class="text-body-m text-cool-90">{{ formatDateTime(message.receivedAt) }}</p>
            </div>

            <div class="flex flex-col gap-1">
                <p class="text-body-xs text-cool-60">{{ t('messages.subject') }}</p>
                <p class="text-body-m text-cool-90">{{ message.subject }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <p class="text-body-xs text-cool-60">{{ t('messages.message') }}</p>
                <!-- Dicetak sebagai teks biasa, bukan HTML: isinya datang dari
                     formulir publik dan tidak pernah dipercaya. `whitespace-pre-line`
                     menjaga paragraf pengirim tanpa menafsirkan satu tag pun. -->
                <p class="whitespace-pre-line text-body-m text-cool-90">{{ message.body }}</p>
            </div>
        </CardSection>

        <div class="flex justify-end">
            <AppButton variant="outline" @click="removing = true">{{ t('messages.delete') }}</AppButton>
        </div>

        <ConfirmDialog
            :open="removing"
            variant="deletion"
            :title="t('messages.delete_title')"
            :description="t('messages.delete_body', { name: message.name })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = false"
        />
    </AdminLayout>
</template>
