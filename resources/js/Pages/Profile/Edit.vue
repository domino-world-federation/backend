<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Akun sendiri.
 *
 * **DUA formulir, bukan satu.** Ganti sandi menuntut sandi lama ikut
 * dibuktikan, dan menggabungkannya dengan nama/surel berarti satu ketikan
 * salah di "sandi sekarang" ikut membatalkan perbaikan nama yang benar. Dua
 * `useForm` juga membuat tiap tombol Simpan mengatakan apa yang ia simpan.
 *
 * **Peran, 2FA, dan status akun digambar TAPI mati.** Bukan hiasan: tanpanya
 * layar ini memunculkan pertanyaan yang jawabannya ada di layar lain — "peran
 * saya apa", "2FA saya sudah aktif belum" — dan orang akan mencarinya dengan
 * menekan-nekan field yang ada. Yang menegakkan bahwa keempatnya tidak bisa
 * diubah dari sini adalah daftar putih di `ProfileController`, bukan layar ini;
 * ada tesnya yang mengirim keempatnya langsung ke endpoint-nya.
 */
const props = defineProps<{
    profile: { name: string; email: string; avatarUrl: string | null }
    account: { roles: string[]; twoFactor: string; lastLoginAt: string | null }
}>()

const { t, locale } = useI18n()

const profile = useForm<{
    name: string
    email: string
    avatar: File | null
    remove_avatar: boolean
}>({
    name: props.profile.name,
    email: props.profile.email,
    avatar: null,
    remove_avatar: false,
})

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
})

const initials = computed(() =>
    (props.profile.name || '')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
)

const lastLogin = computed(() => {
    if (props.account.lastLoginAt === null) return t('profile.never')

    return new Date(props.account.lastLoginAt).toLocaleString(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    })
})

/**
 * `POST` dengan `_method`, bukan `PUT`.
 *
 * `PUT` tidak membawa berkas — pola yang sama dipakai News, Documents dan
 * Gallery, dan alasannya sudah dicatat di CONVENTIONS.
 */
function saveProfile(): void {
    profile.post('/profile', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            // Disemai ulang dari props yang baru: tanpa ini kotaknya
            // memperlihatkan apa yang diketik, bukan apa yang tersimpan.
            profile.defaults({
                name: props.profile.name,
                email: props.profile.email,
                avatar: null,
                remove_avatar: false,
            })
            profile.reset()
        },
    })
}

function savePassword(): void {
    password.put('/profile/password', {
        preserveScroll: true,
        // Sandi tidak pernah ditinggal di kotaknya setelah tersimpan, dan juga
        // tidak setelah gagal: satu-satunya yang perlu diketik ulang.
        onFinish: () => password.reset(),
    })
}
</script>

<template>
    <Head :title="t('profile.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('profile.heading')"
            :breadcrumbs="[{ label: t('profile.title') }]"
        >
            <template #description>{{ t('profile.hint') }}</template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <CardSection :title="t('profile.account')">
                <FormRow :label="t('profile.name')" required>
                    <AppField
                        v-model="profile.name"
                        :error="profile.errors.name"
                        autocomplete="name"
                        required
                    />
                </FormRow>

                <FormRow
                    :label="t('profile.email')"
                    :description="t('profile.email_hint')"
                    required
                >
                    <AppField
                        v-model="profile.email"
                        type="email"
                        :error="profile.errors.email"
                        autocomplete="email"
                        required
                    />
                </FormRow>

                <FormRow
                    :label="t('profile.avatar')"
                    :description="t('profile.avatar_hint', { width: 256, height: 256 })"
                    compact
                >
                    <div class="flex flex-col gap-3">
                        <MediaUpload
                            v-model="profile.avatar"
                            :existing-url="profile.remove_avatar ? null : props.profile.avatarUrl"
                            :fallback-initials="initials"
                            :error="profile.errors.avatar"
                            accept="image/webp"
                        />

                        <!-- Kotak centang terpisah: tidak ada cara lain
                             menyatakan "buang foto saya" lewat sebuah input
                             berkas, dan mengunggah berkas kosong bukan cara. -->
                        <AppCheckbox
                            v-if="props.profile.avatarUrl && profile.avatar === null"
                            v-model="profile.remove_avatar"
                            :label="t('profile.avatar_remove')"
                        />
                    </div>
                </FormRow>

                <div class="flex justify-end">
                    <AppButton :disabled="profile.processing" @click="saveProfile">
                        {{ t('common.save') }}
                    </AppButton>
                </div>
            </CardSection>

            <CardSection :title="t('profile.password_section')">
                <ContextNote>{{ t('profile.password_hint') }}</ContextNote>

                <FormRow :label="t('profile.current_password')" required>
                    <AppField
                        v-model="password.current_password"
                        type="password"
                        :error="password.errors.current_password"
                        autocomplete="current-password"
                        required
                    />
                </FormRow>

                <FormRow :label="t('profile.new_password')" required>
                    <AppField
                        v-model="password.password"
                        type="password"
                        :error="password.errors.password"
                        autocomplete="new-password"
                        required
                    />
                </FormRow>

                <FormRow :label="t('profile.confirm_password')" required>
                    <AppField
                        v-model="password.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                </FormRow>

                <div class="flex justify-end">
                    <AppButton :disabled="password.processing" @click="savePassword">
                        {{ t('profile.change_password') }}
                    </AppButton>
                </div>
            </CardSection>

            <CardSection :title="t('profile.read_only')">
                <ContextNote>{{ t('profile.read_only_hint') }}</ContextNote>

                <dl class="flex flex-col gap-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:gap-6">
                        <dt class="w-[280px] shrink-0 text-body-s text-cool-60">
                            {{ t('profile.roles') }}
                        </dt>
                        <dd class="text-body-s text-cool-90">{{ account.roles.join(', ') }}</dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:gap-6">
                        <dt class="w-[280px] shrink-0 text-body-s text-cool-60">
                            {{ t('profile.two_factor') }}
                        </dt>
                        <!-- Bukan `StatusPill`: pil itu menerjemahkan
                             sendiri dari kunci `news.status_*`, jadi ia tidak
                             bisa mencetak kalimat yang bukan status penayangan.
                             Memaksakannya berarti "Posted" untuk 2FA yang
                             terdaftar. -->
                        <dd class="text-body-s text-cool-90">
                            {{
                                account.twoFactor === 'enrolled'
                                    ? t('profile.two_factor_enrolled')
                                    : t('profile.two_factor_setup_required')
                            }}
                        </dd>
                    </div>

                    <div class="flex flex-col gap-1 sm:flex-row sm:gap-6">
                        <dt class="w-[280px] shrink-0 text-body-s text-cool-60">
                            {{ t('profile.last_login') }}
                        </dt>
                        <dd class="text-body-s text-cool-90">{{ lastLogin }}</dd>
                    </div>
                </dl>
            </CardSection>
        </div>

        <UnsavedGuard :dirty="profile.isDirty || password.isDirty" />
    </AdminLayout>
</template>
