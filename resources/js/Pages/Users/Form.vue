<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import SelectField from '@/Components/SelectField.vue'
import ContextNote from '@/Components/ContextNote.vue'
import RecordMeta from '@/Components/RecordMeta.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

const props = defineProps<{
    user: {
        id: number
        name: string
        email: string
        roles: string[]
        memberFederationId: number | null
        twoFactorEnabled: boolean
        twoFactorEnrolled: boolean
        isActive: boolean
        isSelf: boolean
        createdAt: string | null
        lastLoginAt: string | null
        pendingInvitation: boolean
    } | null
    roles: Array<{ value: string; label: string; scope: string }>
    federations: Array<{ value: number; label: string }>
}>()

const { t } = useI18n()

const isEdit = props.user !== null
const title = computed(() => (isEdit ? t('users.edit') : t('users.add_title')))

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    roles: [...(props.user?.roles ?? [])],
    member_federation_id: props.user?.memberFederationId ?? null,
    two_factor_enabled: props.user?.twoFactorEnabled ?? true,
    is_active: props.user?.isActive ?? true,
})

/**
 * Field "Federation Scope" muncul hanya kalau salah satu peran yang DIPILIH
 * berlingkup federasi — "Required for federation-scoped roles such as Admin PB"
 * (`529:9696`).
 *
 * Dihitung dari `scope` yang dikirim server, bukan dari nama peran yang ditulis
 * di sini: lingkupnya kolom di tabel `roles` dan bisa diubah lewat layar Roles,
 * jadi daftar nama di frontend akan basi tanpa ada yang tahu.
 */
const needsFederation = computed(() =>
    props.roles.some((r) => form.roles.includes(r.value) && r.scope === 'federation'),
)

function toggleRole(role: string, checked: boolean): void {
    form.roles = checked ? [...form.roles, role] : form.roles.filter((r) => r !== role)
}

function submit(): void {
    if (isEdit) {
        form.put(`/users/${props.user!.id}`)
        return
    }

    // Sandi tidak pernah dikirim saat membuat — server menolaknya (`prohibited`),
    // dan alasannya di panel Invitation Flow di bawah.
    form.transform((data) => {
        const { password, ...rest } = data
        void password

        return rest
    }).post('/users')
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('users.group') },
                { label: t('users.title'), href: '/users' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('users.data')">
                <!-- Email LEBIH DULU dari nama — urutan `529:9653`. Di layar
                     ini emailnya bukan sekadar identitas: ia alamat yang akan
                     menerima undangannya, jadi ia keputusan pertama. -->
                <FormRow :label="t('users.email')" :description="t('users.email_hint')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            placeholder="admin@example.com"
                            :error="form.errors.email"
                            autofocus
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('users.name')" :description="t('users.name_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.name" :error="form.errors.name" />
                    </template>
                </FormRow>

                <FormRow :label="t('users.roles')" :description="t('users.roles_hint')" required compact>
                    <div class="flex flex-col gap-3">
                        <AppCheckbox
                            v-for="role in roles"
                            :key="role.value"
                            :model-value="form.roles.includes(role.value)"
                            :label="role.label"
                            @update:model-value="toggleRole(role.value, $event)"
                        />

                        <p v-if="form.errors.roles" role="alert" class="text-body-xs text-danger">
                            {{ form.errors.roles }}
                        </p>
                    </div>
                </FormRow>

                <FormRow
                    v-if="needsFederation"
                    :label="t('users.federation')"
                    :description="t('users.federation_hint')"
                    required
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.member_federation_id"
                            :options="federations"
                            :placeholder="t('users.federation_none')"
                            :error="form.errors.member_federation_id"
                        />
                    </template>
                </FormRow>

                <!-- Sandi HANYA saat menyunting. Saat membuat, panel di bawah
                     yang menjelaskan kenapa ia tidak ada. -->
                <FormRow
                    v-if="isEdit"
                    :label="t('users.password')"
                    :description="t('users.password_hint_edit')"
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            :error="form.errors.password"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('users.two_factor')"
                    :description="t('users.two_factor_hint')"
                    compact
                >
                    <div class="flex flex-col gap-2">
                        <AppToggle v-model="form.two_factor_enabled" :label="t('common.active')" />

                        <p v-if="isEdit" class="text-body-xs text-cool-60">
                            {{
                                user?.twoFactorEnrolled
                                    ? t('users.two_factor_enrolled')
                                    : t('users.two_factor_not_enrolled')
                            }}
                        </p>
                    </div>
                </FormRow>

                <FormRow
                    :label="t('common.status')"
                    :description="t('users.status_hint')"
                    compact
                >
                    <div class="flex flex-col gap-2">
                        <AppToggle
                            v-model="form.is_active"
                            :label="t('common.active')"
                            :disabled="user?.isSelf"
                        />

                        <p v-if="form.errors.is_active" role="alert" class="text-body-xs text-danger">
                            {{ form.errors.is_active }}
                        </p>
                    </div>
                </FormRow>

                <!-- Panel `529:9714`. Ia menggantikan field sandi, dan itu
                     sebabnya ia hanya muncul di layar tambah. -->
                <ContextNote v-if="!isEdit" :title="t('invitation.title')">
                    {{ t('invitation.note') }}
                </ContextNote>

                <RecordMeta
                    v-if="isEdit"
                    :items="[
                        { label: t('news.created'), at: user?.createdAt },
                        {
                            label: t('users.last_login'),
                            value: user?.lastLoginAt ? null : t('users.first_login_pending'),
                            at: user?.lastLoginAt,
                        },
                    ]"
                />
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/users" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">
                    {{ isEdit ? t('common.save') : t('users.add') }}
                </AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
