<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { PhPlus, PhImageSquare } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import DataTable from '@/Components/DataTable.vue'
import RowMenu from '@/Components/RowMenu.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useI18n } from '@/composables/useI18n'
import type { TableColumn } from '@/types'

interface Row {
    id: number
    year: string
    title: string
    summary: string
    imageUrl: string | null
    imageAlt: string | null
    isActive: boolean
    updatedAt: string | null
    updatedBy: string | null
}

defineProps<{ milestones: Row[] }>()

const { t } = useI18n()

const columns: TableColumn[] = [
    { key: 'year', label: t('blocks.milestone_year'), width: '110px' },
    { key: 'title', label: t('blocks.milestone_title') },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

/** `null` = formulir tambah; baris = sedang menyunting yang itu. */
const editing = ref<Row | null>(null)
const open = ref(false)

const form = useForm({
    year: '',
    title: '',
    summary: '',
    image: null as File | null,
    image_alt: '',
    is_active: true,
})

function startAdd(): void {
    editing.value = null
    form.defaults({ year: '', title: '', summary: '', image: null, image_alt: '', is_active: true })
    form.reset()
    form.clearErrors()
    open.value = true
}

function startEdit(row: Row): void {
    editing.value = row
    form.defaults({ year: row.year, title: row.title, summary: row.summary, image: null, image_alt: row.imageAlt ?? '', is_active: row.isActive })
    form.reset()
    form.clearErrors()
    open.value = true
}

function submit(): void {
    // Keduanya POST: `PUT` tidak membawa berkas, dan gambarnya berkas.
    form.post(editing.value ? `/blocks/heritage/${editing.value.id}` : '/blocks/heritage', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
        },
    })
}

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/blocks/heritage/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('blocks.heritage_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('blocks.heritage_title')"
            :breadcrumbs="[{ label: t('blocks.title') }, { label: t('blocks.heritage') }]"
        >
            <template #description>{{ t('blocks.heritage_hint') }}</template>
            <template #actions>
                <AppButton @click="startAdd">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('blocks.add_milestone') }}
                </AppButton>
            </template>
        </PageHeader>

        <form v-if="open" class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="editing ? t('blocks.edit_milestone') : t('blocks.add_milestone')">
                <FormRow
                    :label="t('blocks.milestone_year')"
                    :description="t('blocks.milestone_year_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.year" placeholder="1974" :error="form.errors.year" autofocus />
                    </template>
                </FormRow>

                <FormRow :label="t('blocks.milestone_title')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.title" :error="form.errors.title" />
                    </template>
                </FormRow>

                <FormRow :label="t('blocks.milestone_summary')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.summary" textarea :error="form.errors.summary" />
                    </template>
                </FormRow>

                <FormRow :label="t('blocks.milestone_image')">
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.image"
                            kind="image"
                            :existing-url="editing?.imageUrl"
                            :error="form.errors.image"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('blocks.milestone_alt')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.image_alt" :error="form.errors.image_alt" />
                    </template>
                </FormRow>

                <FormRow :label="t('common.status')" compact>
                    <AppToggle v-model="form.is_active" :label="t('common.active')" />
                </FormRow>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton variant="outline" @click="open = false">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <DataTable
            :columns="columns"
            :rows="milestones"
            row-key="id"
            :empty-message="t('blocks.heritage_empty')"
        >
            <template #cell.year="{ row }">
                <span class="text-body-s text-cool-90">{{ row.year }}</span>
            </template>

            <template #cell.title="{ row }">
                <span class="flex items-center gap-3">
                    <span class="flex h-9 w-14 shrink-0 items-center justify-center overflow-hidden bg-cool-10">
                        <img
                            v-if="row.imageUrl"
                            :src="row.imageUrl"
                            :alt="row.imageAlt ?? ''"
                            class="size-full object-cover"
                        />
                        <PhImageSquare v-else :size="18" class="text-cool-40" aria-hidden="true" />
                    </span>
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate text-body-s text-cool-90">{{ row.title }}</span>
                        <span class="truncate text-body-xs text-cool-60">{{ row.summary }}</span>
                    </span>
                </span>
            </template>

            <template #cell.status="{ row }">
                <span
                    class="inline-flex border px-2 py-1 text-body-xs"
                    :class="
                        row.isActive
                            ? 'border-transparent bg-cool-10 text-cool-90'
                            : 'border-cool-30 bg-transparent text-cool-60'
                    "
                >
                    {{ row.isActive ? t('common.active') : t('common.inactive') }}
                </span>
            </template>

            <template #cell.updated="{ row }">
                <PersonStamp :name="row.updatedBy" :at="row.updatedAt" />
            </template>

            <template #cell.actions="{ row }">
                <RowMenu :label="t('common.row_actions', { name: row.title })">
                    <button
                        type="button"
                        class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-cool-90 hover:bg-cool-10"
                        @click="startEdit(row as Row)"
                    >
                        {{ t('common.edit') }}
                    </button>
                    <button
                        type="button"
                        class="block w-full cursor-pointer px-4 py-2 text-left text-body-s text-danger hover:bg-cool-10"
                        @click="removing = row as Row"
                    >
                        {{ t('common.delete') }}
                    </button>
                </RowMenu>
            </template>
        </DataTable>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('blocks.milestone_delete_title')"
            :description="t('blocks.milestone_delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
