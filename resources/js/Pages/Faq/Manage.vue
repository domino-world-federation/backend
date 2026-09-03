<script setup lang="ts">
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ReorderList from '@/Components/ReorderList.vue'
import ContextNote from '@/Components/ContextNote.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'

const props = defineProps<{ faqs: Array<{ id: number; label: string; note?: string | null }> }>()

const { t } = useI18n()

const order = ref<number[]>(props.faqs.map((f) => f.id))
const dirty = ref(false)
const saving = ref(false)

function save(): void {
    saving.value = true
    router.put(
        '/faq/reorder',
        { ids: order.value },
        {
            preserveScroll: true,
            onSuccess: () => (dirty.value = false),
            onFinish: () => (saving.value = false),
        },
    )
}
</script>

<template>
    <Head :title="t('faq.order_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('faq.order_title')"
            :breadcrumbs="[
                { label: t('faq.title'), href: '/faq' },
                { label: t('faq.list'), href: '/faq' },
                { label: t('faq.order_title') },
            ]"
        />

        <div class="flex flex-col items-end gap-6">
            <CardSection :title="t('faq.order_card')">
                <p class="text-body-s text-cool-100">
                    {{ t('order.hint') }} {{ t('faq.order_hint') }}
                </p>

                <!-- Dikatakan di layarnya sendiri, bukan cuma di dokumen:
                     daftar ini memuat SELURUH FAQ, jadi tanpa kalimat ini
                     orang wajar mengira menyeret di sini juga yang mengatur
                     urutan di Home dan Domino. Dulu memang begitu; sekarang
                     tidak lagi. -->
                <ContextNote>{{ t('faq.order_note') }}</ContextNote>

                <ReorderList
                    :items="faqs"
                    @change="
                        (ids) => {
                            order = ids
                            dirty = true
                        }
                    "
                />
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/faq" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton :disabled="!dirty || saving" @click="save">{{ t('common.save_order') }}</AppButton>
            </div>
        </div>
        <!-- Urutan yang sudah diseret tapi belum disimpan sama saja dengan
             isian formulir yang belum disimpan: hilang tanpa jejak kalau
             halamannya ditinggalkan. -->
        <UnsavedGuard :dirty="dirty" />
    </AdminLayout>
</template>
