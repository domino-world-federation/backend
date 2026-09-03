<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { PhDownloadSimple, PhPlus, PhTrash } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

interface Row {
    year: string
    event: string
    category: string
    winners: string
    federation: string
    is_active: boolean
}

const props = defineProps<{ results: Row[] }>()

const { t } = useI18n()

const form = useForm({ results: props.results.map((r) => ({ ...r })) })

function addRow(): void {
    form.results = [
        ...form.results,
        { year: '', event: '', category: '', winners: '', federation: '', is_active: true },
    ]
}

function removeRow(index: number): void {
    form.results = form.results.filter((_, i) => i !== index)
}

function submit(): void {
    form.put('/results/olympic', { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('results.olympic_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('results.olympic_title')"
            :breadcrumbs="[
                { label: t('results.title') },
                { label: t('results.list'), href: '/results' },
                { label: t('results.olympic') },
            ]"
        >
            <template #actions>
                <AppButton href="/results/olympic/export" variant="outline" external>
                    <template #iconLeft><PhDownloadSimple :size="24" /></template>
                    {{ t('news.export') }}
                </AppButton>
            </template>
        </PageHeader>

        <ContextNote>{{ t('results.olympic_hint') }}</ContextNote>

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('results.olympic_title')">
                <p v-if="form.results.length === 0" class="text-body-s text-cool-60">
                    {{ t('results.olympic_empty') }}
                </p>

                <div
                    v-for="(row, index) in form.results"
                    :key="index"
                    class="flex w-full flex-col gap-3 border-b border-cool-20 pb-4 last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-subtitle-s text-cool-90">#{{ index + 1 }}</span>
                        <div class="flex items-center gap-4">
                            <AppToggle v-model="row.is_active" :label="t('common.active')" />
                            <button
                                type="button"
                                class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                                :aria-label="t('results.remove_olympic', { name: row.event || `#${index + 1}` })"
                                @click="removeRow(index)"
                            >
                                <PhTrash :size="16" aria-hidden="true" />
                                {{ t('common.delete') }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[110px_1fr_1fr]">
                        <AppField
                            v-model="row.year"
                            :aria-label="t('results.olympic_year')"
                            placeholder="2024"
                            :error="(form.errors as any)[`results.${index}.year`]"
                        />
                        <AppField
                            v-model="row.event"
                            :aria-label="t('results.olympic_event')"
                            :placeholder="t('results.olympic_event')"
                            :error="(form.errors as any)[`results.${index}.event`]"
                        />
                        <AppField
                            v-model="row.category"
                            :aria-label="t('results.olympic_category')"
                            :placeholder="t('results.olympic_category')"
                            :error="(form.errors as any)[`results.${index}.category`]"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <AppField
                            v-model="row.winners"
                            :aria-label="t('results.olympic_winners')"
                            :placeholder="t('results.olympic_winners')"
                            :error="(form.errors as any)[`results.${index}.winners`]"
                        />
                        <AppField
                            v-model="row.federation"
                            :aria-label="t('results.olympic_federation')"
                            :placeholder="t('results.olympic_federation')"
                            :error="(form.errors as any)[`results.${index}.federation`]"
                        />
                    </div>
                </div>

                <AppButton variant="outline" size="s" @click="addRow">
                    <template #iconLeft><PhPlus :size="20" /></template>
                    {{ t('results.add_olympic') }}
                </AppButton>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/results" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
