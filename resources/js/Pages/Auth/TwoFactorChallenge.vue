<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import CodeInput from '@/Components/CodeInput.vue'
import { useI18n } from '@/composables/useI18n'

const { t } = useI18n()

const form = useForm({ code: '' })

function submit(): void {
    form.post('/two-factor/challenge', {
        onError: () => form.reset('code'),
    })
}
</script>

<template>
    <Head :title="t('two_factor.challenge_title')" />

    <AuthLayout>
        <form class="flex flex-col items-center gap-6 bg-surface p-6" @submit.prevent="submit">
            <div class="flex flex-col items-center gap-2 text-center">
                <h1 class="text-heading-6 text-cool-90">{{ t('two_factor.challenge_title') }}</h1>
                <p class="text-body-xs text-cool-60">{{ t('two_factor.challenge_hint') }}</p>
            </div>

            <CodeInput v-model="form.code" :error="form.errors.code" />

            <AppButton type="submit" class="w-full" :disabled="form.processing || !form.code">
                {{ t('two_factor.confirm') }}
            </AppButton>

            <p class="text-center text-body-xs text-cool-60">
                {{ t('two_factor.recovery_hint_challenge') }}
            </p>
        </form>
    </AuthLayout>
</template>
