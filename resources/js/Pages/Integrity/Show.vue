<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ContextNote from '@/Components/ContextNote.vue'
import AppButton from '@/Components/AppButton.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { formatDateTime } from '@/utils/format'

/**
 * Satu laporan integritas.
 *
 * Tidak ada tombol "Balas" — bandingkan dengan Contact Messages, yang punya.
 * Laporannya anonim, jadi tombol yang membuka `mailto:` akan menjanjikan alamat
 * yang tidak ada di baris mana pun.
 *
 * Isinya dicetak dengan `{{ }}`, TIDAK PERNAH sebagai HTML: ia diketik orang
 * asing di internet, dan tidak ada satu pun alasan ia perlu diformat.
 */
const props = defineProps<{
    report: {
        id: number
        type: string
        description: string
        receivedAt: string | null
    }
}>()

const { t } = useI18n()

const removing = ref(false)
const processing = ref(false)

function destroy(): void {
    processing.value = true
    router.delete(`/integrity-reports/${props.report.id}`, {
        onFinish: () => {
            processing.value = false
            removing.value = false
        },
    })
}
</script>

<template>
    <Head :title="t('integrity.detail')" />

    <AdminLayout>
        <PageHeader
            :title="report.type"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('integrity.title'), href: '/integrity-reports' },
                { label: t('integrity.detail') },
            ]"
        >
            <template #actions>
                <AppButton href="/integrity-reports" variant="outline">
                    {{ t('common.back') }}
                </AppButton>
                <AppButton variant="outline" @click="removing = true">
                    {{ t('common.delete') }}
                </AppButton>
            </template>
        </PageHeader>

        <CardSection :title="t('integrity.detail')">
            <ContextNote tone="security">{{ t('integrity.anonymous_note') }}</ContextNote>

            <div class="flex flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <p class="text-body-xs text-cool-60">{{ t('integrity.incident_type') }}</p>
                    <p class="text-body-m text-cool-90">{{ report.type }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <p class="text-body-xs text-cool-60">{{ t('integrity.received') }}</p>
                    <p class="text-body-m text-cool-90">{{ formatDateTime(report.receivedAt) }}</p>
                </div>

                <div class="flex flex-col gap-1">
                    <p class="text-body-xs text-cool-60">{{ t('integrity.description') }}</p>
                    <p class="text-body-m whitespace-pre-line text-cool-90">{{ report.description }}</p>
                </div>
            </div>
        </CardSection>

        <ConfirmDialog
            :open="removing"
            variant="deletion"
            :title="t('integrity.delete_title')"
            :description="t('integrity.delete_body')"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = false"
        />
    </AdminLayout>
</template>
