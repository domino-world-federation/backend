<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { PhWarningCircle } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import SelectField from '@/Components/SelectField.vue'
import AppField from '@/Components/AppField.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

interface Module {
    key: string
    label: string
    actions: Array<{ value: string; label: string }>
}

const props = defineProps<{
    role: {
        id: number
        name: string
        permissions: string[]
        scope: string
        summary: string | null
        type: string
        isSystem: boolean
        isSuperAdmin: boolean
    } | null
    matrix: Module[]
}>()

const { t } = useI18n()

const isEdit = props.role !== null
const title = computed(() => (isEdit ? t('roles.edit') : t('roles.add')))

const form = useForm({
    name: props.role?.name ?? '',
    scope: props.role?.scope ?? 'global',
    summary: props.role?.summary ?? '',
    permissions: [...(props.role?.permissions ?? [])],
})

const scopeOptions = computed(() => [
    { value: 'global', label: t('roles.scope_global') },
    { value: 'federation', label: t('roles.scope_federation') },
])

function has(permission: string): boolean {
    return form.permissions.includes(permission)
}

function toggle(permission: string, checked: boolean): void {
    form.permissions = checked
        ? [...form.permissions, permission]
        : form.permissions.filter((p) => p !== permission)
}

/** Centang/kosongkan seluruh baris sekaligus — 29 kotak itu banyak. */
function setModule(module: Module, checked: boolean): void {
    const values = module.actions.map((a) => a.value)

    form.permissions = checked
        ? [...new Set([...form.permissions, ...values])]
        : form.permissions.filter((p) => !values.includes(p))
}

function moduleState(module: Module): 'all' | 'some' | 'none' {
    const on = module.actions.filter((a) => has(a.value)).length
    if (on === 0) return 'none'
    return on === module.actions.length ? 'all' : 'some'
}

function submit(): void {
    if (isEdit) {
        form.put(`/roles/${props.role!.id}`)
        return
    }
    form.post('/roles')
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('roles.title'), href: '/roles' },
                { label: title },
            ]"
        />

        <!-- super-admin dilewatkan `Gate::before`, jadi baris izinnya tidak
             pernah dibaca. Layar sunting yang tidak mengubah apa pun adalah
             bentuk kebohongan yang paling membingungkan. -->
        <div
            v-if="role?.isSuperAdmin"
            class="flex items-start gap-3 border-l-4 border-primary-60 bg-surface px-4 py-3"
            role="note"
        >
            <PhWarningCircle :size="20" class="mt-0.5 shrink-0 text-cool-60" aria-hidden="true" />
            <p class="text-body-s text-cool-90">{{ t('roles.super_admin_note') }}</p>
        </div>

        <form v-else class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('roles.data')">
                <FormRow :label="t('roles.name')" :description="t('roles.name_hint')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.name"
                            placeholder="content-lead"
                            :error="form.errors.name"
                            autofocus
                        />
                    </template>
                </FormRow>

                <!-- Lingkup menentukan apakah admin yang memakai peran ini
                     wajib dipilihkan federasinya (`529:9696`). -->
                <FormRow :label="t('roles.scope')" :description="t('roles.scope_hint')" required>
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.scope"
                            :options="scopeOptions"
                            :error="form.errors.scope"
                        />
                    </template>
                </FormRow>

                <!-- Kolom "Permission Summary" di daftar. Ditulis orang, BUKAN
                     dirangkai dari centang di bawah: desain menulis "Players,
                     KYC, federation content" untuk peran berizin belasan, jadi
                     yang dicari pembacanya niat perannya, bukan cerminan
                     datanya. -->
                <FormRow :label="t('roles.summary')" :description="t('roles.summary_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.summary" :error="form.errors.summary" />
                    </template>
                </FormRow>
            </CardSection>

            <CardSection :title="t('roles.permissions')">
                <p class="text-body-s text-cool-60">{{ t('roles.permissions_hint') }}</p>

                <div class="w-full overflow-x-auto border border-cool-20">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th
                                    scope="col"
                                    class="border-b border-cool-20 bg-cool-10 px-3 py-3 text-left text-subtitle-s text-cool-100"
                                >
                                    {{ t('roles.module') }}
                                </th>
                                <th
                                    v-for="action in ['view', 'create', 'update', 'delete']"
                                    :key="action"
                                    scope="col"
                                    class="w-24 border-b border-cool-20 bg-cool-10 px-3 py-3 text-center text-subtitle-s text-cool-100"
                                >
                                    {{ t(`roles.action_${action}`) }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr v-for="module in matrix" :key="module.key">
                                <th
                                    scope="row"
                                    class="border-t border-cool-20 px-3 py-2 text-left font-normal"
                                >
                                    <AppCheckbox
                                        :model-value="moduleState(module) === 'all'"
                                        :label="module.label"
                                        @update:model-value="setModule(module, $event)"
                                    />
                                </th>

                                <td
                                    v-for="action in ['view', 'create', 'update', 'delete']"
                                    :key="action"
                                    class="border-t border-cool-20 px-3 py-2 text-center"
                                >
                                    <!-- Modul yang tidak punya aksi ini
                                         menampilkan garis, bukan kotak mati:
                                         kotak yang tidak bisa dicentang membuat
                                         orang mengira ada yang rusak. -->
                                    <template
                                        v-if="module.actions.some((a) => a.label === action)"
                                    >
                                        <AppCheckbox
                                            :model-value="has(`${module.key}.${action}`)"
                                            :label="`${module.label} — ${t(`roles.action_${action}`)}`"
                                            hide-label
                                            @update:model-value="
                                                toggle(`${module.key}.${action}`, $event)
                                            "
                                        />
                                    </template>
                                    <span v-else class="text-body-xs text-cool-30">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="form.errors.permissions" role="alert" class="text-body-xs text-danger">
                    {{ form.errors.permissions }}
                </p>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/roles" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
