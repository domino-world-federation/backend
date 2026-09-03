<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import SelectField from '@/Components/SelectField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import RecordMeta from '@/Components/RecordMeta.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import ContextNote from '@/Components/ContextNote.vue'
import { useI18n } from '@/composables/useI18n'

const props = defineProps<{
    rule: {
        id: number
        name: string
        ipRange: string
        scope: string
        roleId: number | null
        userId: number | null
        validity: string
        expiresAt: string | null
        notes: string | null
        isActive: boolean
        createdBy: string | null
        createdAt: string | null
        updatedBy: string | null
        updatedAt: string | null
    } | null
    roles: Array<{ value: number; label: string }>
    admins: Array<{ value: number; label: string }>
    currentIp: string | null
}>()

const { t } = useI18n()

const isEdit = props.rule !== null
const title = computed(() => (isEdit ? t('ip_whitelist.edit') : t('ip_whitelist.add_title')))

const form = useForm({
    name: props.rule?.name ?? '',
    ip_range: props.rule?.ipRange ?? '',
    scope: props.rule?.scope ?? 'all_admins',
    role_id: props.rule?.roleId ?? null,
    user_id: props.rule?.userId ?? null,
    validity: props.rule?.validity ?? 'permanent',
    expires_at: props.rule?.expiresAt ?? '',
    notes: props.rule?.notes ?? '',
    is_active: props.rule?.isActive ?? true,
})

/*
 * Dua baris di bawah muncul dan hilang mengikuti pilihan di atasnya — persis
 * yang dijanjikan hint desainnya: "Required for Specific Role. When Specific
 * Admin is selected, this field becomes an Admin selector."
 *
 * Baris yang tidak berlaku DIBUANG, bukan dinonaktifkan. Field mati yang tetap
 * tergambar mengundang orang mengisinya lebih dulu lalu bingung kenapa tidak
 * bisa; dan ruang yang ia sisakan membuat kartu ini terlihat separuh rusak.
 */
const needsRole = computed(() => form.scope === 'role')
const needsAdmin = computed(() => form.scope === 'user')
const needsExpiry = computed(() => form.validity === 'temporary')

const scopeOptions = computed(() => [
    { value: 'all_admins', label: t('ip_whitelist.scope_all') },
    { value: 'role', label: t('ip_whitelist.scope_role') },
    { value: 'user', label: t('ip_whitelist.scope_user') },
])

const validityOptions = computed(() => [
    { value: 'permanent', label: t('ip_whitelist.permanent') },
    { value: 'temporary', label: t('ip_whitelist.temporary') },
])

function submit(): void {
    if (isEdit) {
        form.put(`/ip-whitelist/${props.rule!.id}`)
        return
    }
    form.post('/ip-whitelist')
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('users.group') },
                { label: t('ip_whitelist.list'), href: '/ip-whitelist' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('ip_whitelist.data')">
                <FormRow
                    :label="t('ip_whitelist.name')"
                    :description="t('ip_whitelist.name_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.name" :error="form.errors.name" autofocus />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('ip_whitelist.ip')"
                    :description="t('ip_whitelist.ip_hint')"
                    required
                >
                    <template #default="{ id }">
                        <div class="flex flex-col gap-1">
                            <AppField
                                :id="id"
                                v-model="form.ip_range"
                                class="font-mono"
                                placeholder="203.0.113.0/24"
                                autocomplete="off"
                                spellcheck="false"
                                :error="form.errors.ip_range"
                            />
                            <!-- Alamat sendiri dicetak di sebelah kotaknya
                                 supaya orang bisa menyalinnya. Tanpa ini,
                                 aturan pertama yang dibuat siapa pun berisiko
                                 tidak memuat dirinya — dan penjaga di server
                                 akan menolaknya tanpa memberi tahu alamat apa
                                 yang seharusnya ia tulis. -->
                            <p v-if="currentIp" class="text-body-xs text-cool-60">
                                {{ t('ip_whitelist.current_ip', { ip: currentIp }) }}
                            </p>
                        </div>
                    </template>
                </FormRow>

                <FormRow
                    :label="t('ip_whitelist.scope')"
                    :description="t('ip_whitelist.scope_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.scope"
                            :options="scopeOptions"
                            :error="form.errors.scope"
                        />
                    </template>
                </FormRow>

                <FormRow
                    v-if="needsRole"
                    :label="t('ip_whitelist.allowed_role')"
                    :description="t('ip_whitelist.allowed_role_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.role_id"
                            :options="roles"
                            :error="form.errors.role_id"
                        />
                    </template>
                </FormRow>

                <FormRow
                    v-if="needsAdmin"
                    :label="t('ip_whitelist.allowed_admin')"
                    :description="t('ip_whitelist.allowed_role_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.user_id"
                            :options="admins"
                            :error="form.errors.user_id"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('ip_whitelist.validity')"
                    :description="t('ip_whitelist.validity_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.validity"
                            :options="validityOptions"
                            :error="form.errors.validity"
                        />
                    </template>
                </FormRow>

                <FormRow
                    v-if="needsExpiry"
                    :label="t('ip_whitelist.expires_at')"
                    :description="t('ip_whitelist.expires_at_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.expires_at"
                            type="datetime-local"
                            :error="form.errors.expires_at"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('ip_whitelist.notes')" :description="t('ip_whitelist.notes_hint')">
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.notes"
                            textarea
                            :error="form.errors.notes"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('common.status')"
                    :description="t('ip_whitelist.status_hint')"
                    compact
                >
                    <AppToggle v-model="form.is_active" :label="t('common.active')" />
                </FormRow>

                <!-- Panel `527:8161`. Isinya bukan basa-basi: empat dari lima
                     butirnya benar-benar ditegakkan (format, irisan, tanggal
                     masa depan, penjaga kunci-diri-sendiri), dan yang kelima
                     menjelaskan kenapa tidak ada field untuk Created By. -->
                <ContextNote tone="security" :title="t('ip_whitelist.validation_title')">
                    {{ t('ip_whitelist.validation_body') }}
                </ContextNote>

                <!-- "Created By, Created At, and Last Updated are recorded
                     automatically and are read-only" — jadi ditampilkan, bukan
                     disunting. Hanya saat menyunting: baris baru belum punya. -->
                <RecordMeta
                    v-if="isEdit"
                    :items="[
                        { label: t('ip_whitelist.created_by'), value: rule?.createdBy, at: rule?.createdAt },
                        { label: t('ip_whitelist.last_updated'), value: rule?.updatedBy, at: rule?.updatedAt },
                    ]"
                />
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/ip-whitelist" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">
                    {{ t('ip_whitelist.save') }}
                </AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
