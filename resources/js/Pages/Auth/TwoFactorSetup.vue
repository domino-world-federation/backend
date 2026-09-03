<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import CodeInput from '@/Components/CodeInput.vue'
import { useI18n } from '@/composables/useI18n'

defineProps<{ qrSvg: string; secret: string }>()

const { t } = useI18n()

const form = useForm({ code: '' })

function submit(): void {
    form.post('/two-factor/setup', {
        onError: () => form.reset('code'),
    })
}
</script>

<template>
    <Head :title="t('two_factor.setup_title')" />

    <AuthLayout>
        <form class="flex flex-col items-center gap-6 bg-surface p-6" @submit.prevent="submit">
            <div class="flex flex-col items-center gap-2 text-center">
                <h1 class="text-heading-6 text-cool-90">{{ t('two_factor.setup_title') }}</h1>
                <p class="text-body-xs text-cool-60">{{ t('two_factor.setup_hint') }}</p>
            </div>

            <!--
                SVG disuntikkan dengan v-html, dan itu aman DI SINI karena
                sumbernya bukan masukan pengguna: markup-nya dihasilkan
                `bacon/bacon-qr-code` di server kita sendiri dari rahasia yang
                juga kita yang membuatnya. Jangan salin pola ini untuk konten
                yang datang dari luar.
            -->
            <div class="size-[220px] [&>svg]:size-full" aria-hidden="true" v-html="qrSvg" />

            <div class="flex w-full flex-col items-center gap-1">
                <p class="text-body-xs text-cool-60">{{ t('two_factor.manual_label') }}</p>
                <code class="text-body-s tracking-widest text-cool-90 select-all">{{ secret }}</code>
            </div>

            <CodeInput v-model="form.code" :error="form.errors.code" />

            <AppButton
                type="submit"
                class="w-full"
                :disabled="form.processing || form.code.length < 6"
            >
                {{ t('two_factor.confirm') }}
            </AppButton>
        </form>
    </AuthLayout>
</template>
