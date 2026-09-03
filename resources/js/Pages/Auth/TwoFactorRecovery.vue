<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthLayout from '@/Layouts/AuthLayout.vue'
import AppButton from '@/Components/AppButton.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Kode pemulihan, ditampilkan SEKALI setelah pendaftaran.
 *
 * Layar ini tidak ada di Figma, tapi tanpanya 2FA punya cacat yang cuma
 * ketahuan pada hari terburuk: kehilangan ponsel berarti kehilangan akun,
 * permanen, tanpa jalan masuk lain.
 */
const props = defineProps<{ codes: string[] }>()

const { t } = useI18n()

const copied = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

onBeforeUnmount(() => clearTimeout(timer))

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.codes.join('\n'))
        copied.value = true
        clearTimeout(timer)
        timer = setTimeout(() => (copied.value = false), 2000)
    } catch {
        // Papan klip diblokir (konteks non-secure). Kodenya tetap terpilih dan
        // bisa disalin manual — tombolnya saja yang tidak bisa membantu.
    }
}
</script>

<template>
    <Head :title="t('two_factor.recovery_title')" />

    <AuthLayout>
        <div class="flex flex-col gap-6 bg-surface p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-heading-6 text-cool-90">{{ t('two_factor.recovery_title') }}</h1>
                <p class="text-body-xs text-cool-60">{{ t('two_factor.recovery_hint') }}</p>
            </div>

            <ul class="grid grid-cols-2 gap-2 border border-cool-20 bg-cool-10 p-4">
                <li
                    v-for="code in codes"
                    :key="code"
                    class="text-center text-body-s tracking-wide text-cool-90 select-all"
                >
                    {{ code }}
                </li>
            </ul>

            <div class="flex flex-col gap-2">
                <AppButton variant="outline" class="w-full" @click="copy">
                    {{ copied ? t('two_factor.recovery_copied') : t('two_factor.recovery_copy') }}
                </AppButton>
                <AppButton class="w-full" @click="router.visit('/dashboard')">
                    {{ t('two_factor.recovery_done') }}
                </AppButton>
            </div>
        </div>
    </AuthLayout>
</template>
