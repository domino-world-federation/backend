<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import RepeaterRows from '@/Components/RepeaterRows.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

interface Row {
    name: string
    /** Dipisah koma di UI; server yang memecahnya jadi array pil. */
    remit: string
    is_active: boolean
}

const props = defineProps<{ committees: Row[] }>()

const { t } = useI18n()

const form = useForm({ committees: props.committees.map((c) => ({ ...c })) })

function add(): void {
    form.committees = [...form.committees, { name: '', remit: '', is_active: true }]
}

function remove(index: number): void {
    form.committees = form.committees.filter((_, i) => i !== index)
}

function move(index: number, delta: number): void {
    const target = index + delta
    if (target < 0 || target >= form.committees.length) return

    const next = [...form.committees]
    ;[next[index], next[target]] = [next[target], next[index]]
    form.committees = next
}

function submit(): void {
    form.put('/people/committees', { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('people.committees')" />

    <AdminLayout>
        <PageHeader
            :title="t('people.committees')"
            :breadcrumbs="[
                { label: t('people.title') },
                { label: t('people.board'), href: '/people' },
                { label: t('people.committees') },
            ]"
        />

        <ContextNote>{{ t('people.committees_hint') }}</ContextNote>

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('people.committees')">
                <RepeaterRows
                    :count="form.committees.length"
                    :empty-message="t('people.committees_empty')"
                    :add-label="t('people.add_committee')"
                    reorderable
                    @add="add"
                    @remove="remove"
                    @move="move"
                >
                    <template #default="{ index }">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-center">
                            <AppField
                                v-model="form.committees[index]!.name"
                                :aria-label="t('people.committee_name')"
                                :placeholder="t('people.committee_name')"
                                :error="(form.errors as any)[`committees.${index}.name`]"
                            />
                            <AppField
                                v-model="form.committees[index]!.remit"
                                :aria-label="t('people.committee_remit')"
                                placeholder="Players, KYC, federation content"
                                :error="(form.errors as any)[`committees.${index}.remit`]"
                            />
                            <AppToggle
                                v-model="form.committees[index]!.is_active"
                                :label="t('common.active')"
                            />
                        </div>
                        <p class="text-body-xs text-cool-60">{{ t('people.committee_remit_hint') }}</p>
                    </template>
                </RepeaterRows>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/people" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
