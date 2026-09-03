<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppField from '@/Components/AppField.vue'

/**
 * Menerima undangan admin — sisi yang dilihat orang yang diundang
 * (`529:9714`, panel "Invitation Flow").
 *
 * Tidak ada desainnya: file Figma menggambar layar yang MENGIRIM undangan,
 * bukan yang menerimanya. Dibangun dengan cangkang dan token yang sama seperti
 * halaman login supaya ia terasa bagian dari aplikasi yang sama, bukan dikarang
 * jadi sesuatu yang nanti harus dibongkar saat desainnya datang.
 */
const props = defineProps<{
    token: string
    name: string
    email: string
    appName: string
}>()

const { t } = useI18n()

const form = useForm({
    password: '',
    password_confirmation: '',
})

function submit(): void {
    form.post(`/invitation/${props.token}`, {
        // Sandi tidak boleh tertinggal di memori form setelah percobaan gagal —
        // alasan yang sama dengan halaman login.
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head :title="t('invitation.accept_title')" />

    <AuthLayout>
        <form class="flex flex-col gap-6 bg-surface p-6" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <h1 class="text-heading-4 text-cool-90">{{ t('invitation.accept_title') }}</h1>
                <p class="text-body-s text-cool-60">
                    {{ t('invitation.accept_intro', { app: props.appName, email }) }}
                </p>
            </div>

            <!-- Nama dicetak sebagai kotak baca-saja, bukan field.
                 Orang yang membuka tautan ini perlu yakin bahwa undangannya
                 memang untuknya — tapi mengizinkannya mengganti nama di sini
                 berarti satu-satunya hal yang diketik super admin bisa ditimpa
                 sebelum ada yang melihatnya. -->
            <div class="flex flex-col gap-2">
                <span class="text-body-s text-cool-70">{{ t('users.name') }}</span>
                <p class="text-body-m text-cool-90">{{ name }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-body-s text-cool-70">
                    {{ t('invitation.password') }}
                </label>
                <AppField
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    required
                    :error="form.errors.password"
                />
                <p class="text-body-xs text-cool-60">{{ t('invitation.password_hint') }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="text-body-s text-cool-70">
                    {{ t('invitation.password_confirmation') }}
                </label>
                <AppField
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                />
            </div>

            <AppButton type="submit" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('invitation.submit') }}
            </AppButton>
        </form>
    </AuthLayout>
</template>
