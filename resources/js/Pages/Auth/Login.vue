<script setup lang="ts">
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import AppField from '@/Components/AppField.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import RecaptchaCheckbox from '@/Components/RecaptchaCheckbox.vue'

/** `null` berarti captcha tidak dikonfigurasi — lihat `Recaptcha::siteKey()`. */
defineProps<{ recaptchaSiteKey: string | null }>()

const { t } = useI18n()

const captcha = ref<InstanceType<typeof RecaptchaCheckbox> | null>(null)

const form = useForm({
    email: '',
    password: '',
    remember: false,
    recaptcha_token: '',
})

function submit(): void {
    form.post('/login', {
        onFinish: () => {
            // Password tidak boleh tertinggal di memori form setelah kunjungan
            // gagal — `useForm` menahannya sampai submit berikutnya.
            form.reset('password')

            // Token reCAPTCHA hanya sah SEKALI. Setelah percobaan gagal,
            // token lama sudah terpakai di sisi Google, jadi tanpa reset ini
            // percobaan kedua selalu ditolak — dan yang terlihat orang adalah
            // "captcha gagal" padahal ia sudah mencentangnya.
            captcha.value?.reset()
        },
    })
}
</script>

<template>
    <Head :title="t('auth.title')" />

    <AuthLayout>
        <form class="flex flex-col gap-6 bg-surface p-6" @submit.prevent="submit">
            <div class="flex flex-col gap-2">
                <h1 class="text-heading-4 text-cool-90">{{ t('auth.title') }}</h1>
                <p class="text-body-s text-cool-60">{{ t('auth.subtitle') }}</p>
            </div>

            <div class="flex flex-col gap-2">
                <label for="email" class="text-body-s text-cool-70">{{ t('auth.email') }}</label>
                <AppField
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder="nama@dwf-domino.org"
                    required
                    :error="form.errors.email"
                />
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="text-body-s text-cool-70">{{ t('auth.password') }}</label>
                <AppField
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                    :error="form.errors.password"
                />
            </div>

            <AppCheckbox v-model="form.remember" :label="t('auth.remember')" />

            <RecaptchaCheckbox
                v-if="recaptchaSiteKey"
                ref="captcha"
                v-model="form.recaptcha_token"
                :site-key="recaptchaSiteKey"
                :error="form.errors.recaptcha_token"
            />

            <AppButton type="submit" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.submit') }}
            </AppButton>
        </form>
    </AuthLayout>
</template>
