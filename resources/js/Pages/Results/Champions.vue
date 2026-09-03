<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { PhPlus, PhTrophy } from '@phosphor-icons/vue'

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
import ContextNote from '@/Components/ContextNote.vue'
import PersonStamp from '@/Components/PersonStamp.vue'
import { useI18n } from '@/composables/useI18n'
import type { TableColumn } from '@/types'

interface Row {
    id: number
    event: string
    name: string
    portraitUrl: string | null
    portraitAlt: string | null
    isActive: boolean
    updatedAt: string | null
    updatedBy: string | null
}

defineProps<{ champions: Row[]; filters: { q: string } }>()

const { t } = useI18n()

const columns: TableColumn[] = [
    { key: 'name', label: t('results.champion_name') },
    { key: 'event', label: t('results.champion_event') },
    { key: 'status', label: t('common.status'), width: '100px' },
    { key: 'updated', label: t('news.last_modified'), width: '180px' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

/** `null` = formulir tambah; angka = sedang menyunting baris itu. */
const editing = ref<Row | null>(null)
const open = ref(false)

const form = useForm({
    event: '',
    name: '',
    portrait: null as File | null,
    portrait_alt: '',
    is_active: true,
})

function startAdd(): void {
    editing.value = null
    form.reset()
    form.clearErrors()
    open.value = true
}

function startEdit(row: Row): void {
    editing.value = row
    form.defaults({
        event: row.event,
        name: row.name,
        portrait: null,
        portrait_alt: row.portraitAlt ?? '',
        is_active: row.isActive,
    })
    form.reset()
    form.clearErrors()
    open.value = true
}

function submit(): void {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            open.value = false
            form.reset()
        },
    }

    // Keduanya POST: `PUT` tidak membawa berkas, dan potretnya berkas.
    form.post(editing.value ? `/results/champions/${editing.value.id}` : '/results/champions', options)
}

const removing = ref<Row | null>(null)
const processing = ref(false)

function destroy(): void {
    if (!removing.value) return
    processing.value = true
    router.delete(`/results/champions/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('results.champions_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('results.champions_title')"
            :breadcrumbs="[
                { label: t('results.title') },
                { label: t('results.list'), href: '/results' },
                { label: t('results.champions') },
            ]"
        >
            <template #description>{{ t('results.champions_hint') }}</template>
            <template #actions>
                <AppButton @click="startAdd">
                    <template #iconLeft><PhPlus :size="24" /></template>
                    {{ t('results.add_champion') }}
                </AppButton>
            </template>
        </PageHeader>

        <!-- R16 di PRD situs publik, diringkas jadi satu kalimat yang dibaca
             orang tepat sebelum ia mengunggah wajah seseorang. -->
        <ContextNote tone="warning">{{ t('results.champions_note') }}</ContextNote>

        <form v-if="open" class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="editing ? t('results.edit_champion') : t('results.add_champion')">
                <FormRow :label="t('results.champion_name')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.name" :error="form.errors.name" autofocus />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('results.champion_event')"
                    :description="t('results.champion_event_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.event" :error="form.errors.event" />
                    </template>
                </FormRow>

                <FormRow :label="t('results.champion_portrait')">
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

                <FormRow :label="t('results.champion_alt')" :description="t('results.champion_alt_hint')">
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
            :rows="champions"
            row-key="id"
            :empty-message="t('results.champions_empty')"
        >
            <template #cell.name="{ row }">
                <span class="flex items-center gap-3">
                    <!-- Kartu tanpa potret jatuh ke panel gradien di situs
                         publik; di sini ia piala abu, bukan gambar rusak. -->
                    <span class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-cool-10">
                        <img
                            v-if="row.portraitUrl"
                            :src="row.portraitUrl"
                            :alt="row.portraitAlt ?? ''"
                            class="size-full object-cover"
                        />
                        <PhTrophy v-else :size="18" class="text-cool-40" aria-hidden="true" />
                    </span>
                    <span class="text-body-s text-cool-90">{{ row.name }}</span>
                </span>
            </template>

            <template #cell.event="{ row }">
                <span class="text-body-s text-cool-70">{{ row.event }}</span>
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
            :title="t('results.champion_delete_title')"
            :description="t('results.champion_delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = null"
        />
    </AdminLayout>
</template>
