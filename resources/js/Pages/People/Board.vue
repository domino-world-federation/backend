<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { PhPlus, PhUser } from '@phosphor-icons/vue'

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
    name: string
    role: string
    portraitUrl: string | null
    portraitAlt: string | null
    isActive: boolean
    updatedAt: string | null
    updatedBy: string | null
}

defineProps<{ members: Row[] }>()

const { t } = useI18n()

const columns: TableColumn[] = [
    { key: 'name', label: t('people.member_name') },
    { key: 'role', label: t('people.member_role') },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

/** `null` = formulir tambah; baris = sedang menyunting yang itu. */
const editing = ref<Row | null>(null)
const open = ref(false)

const form = useForm({
    name: '',
    role: '',
    portrait: null as File | null,
    portrait_alt: '',
    is_active: true,
})

function startAdd(): void {
    editing.value = null
    form.defaults({ name: '', role: '', portrait: null, portrait_alt: '', is_active: true })
    form.reset()
    form.clearErrors()
    open.value = true
}

function startEdit(row: Row): void {
    editing.value = row
    form.defaults({ name: row.name, role: row.role, portrait: null, portrait_alt: row.portraitAlt ?? '', is_active: row.isActive })
    form.reset()
    form.clearErrors()
    open.value = true
}

function submit(): void {
    // Keduanya POST: `PUT` tidak membawa berkas, dan gambarnya berkas.
    form.post(editing.value ? `/people/${editing.value.id}` : '/people', {
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
    router.delete(`/people/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('people.board_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('people.board_title')"
            :breadcrumbs="[{ label: t('people.title') }, { label: t('people.board') }]"
        >
            <template #description>{{ t('people.board_hint') }}</template>
            <template #actions>
                <AppButton @click="startAdd">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('people.add_member') }}
                </AppButton>
            </template>
        </PageHeader>

        <form v-if="open" class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="editing ? t('people.edit_member') : t('people.add_member')">
                <FormRow :label="t('people.member_name')" :description="t('people.member_name_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.name" :error="form.errors.name" autofocus />
                    </template>
                </FormRow>

                <FormRow :label="t('people.member_role')" :description="t('people.member_role_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.role" :error="form.errors.role" />
                    </template>
                </FormRow>

                <FormRow :label="t('people.member_portrait')">
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.portrait"
                            kind="image"
                            :existing-url="editing?.portraitUrl"
                            :error="form.errors.portrait"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('people.member_alt')" :description="t('people.member_alt_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.portrait_alt" :error="form.errors.portrait_alt" />
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
            :rows="members"
            row-key="id"
            :empty-message="t('people.board_empty')"
        >
            <template #cell.name="{ row }">
                <span class="flex items-center gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-cool-10">
                        <img
                            v-if="row.portraitUrl"
                            :src="row.portraitUrl"
                            :alt="row.portraitAlt ?? ''"
                            class="size-full object-cover"
                        />
                        <PhUser v-else :size="18" class="text-cool-40" aria-hidden="true" />
                    </span>
                    <!-- `whitespace-pre-line`: nama boleh dua baris. -->
                    <span class="text-body-s whitespace-pre-line text-cool-90">{{ row.name }}</span>
                </span>
            </template>

            <template #cell.role="{ row }">
                <span class="text-body-s text-cool-70">{{ row.role }}</span>
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
                <RowMenu :label="t('common.row_actions', { name: row.name })">
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
            :title="t('people.member_delete_title')"
            :description="t('people.member_delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
