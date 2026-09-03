<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { PhArrowSquareOut } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ContextNote from '@/Components/ContextNote.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Naskah halaman depan — dan hanya yang tidak dimiliki modul lain.
 *
 * Menggantikan grup "Landing Page" berisi delapan submenu placeholder. Kartu
 * "Dikelola di tempat lain" di bawah bukan basa-basi: pertanyaan pertama orang
 * yang membuka layar ini adalah "kenapa statistiknya tidak ada di sini", dan
 * menjawabnya dengan tautan lebih berguna daripada tidak menyebutnya sama
 * sekali.
 */
const props = defineProps<{
    values: Record<string, string>
    elsewhere: Array<{ key: string; href: string }>
}>()

const { t } = useI18n()

const form = useForm({ ...props.values })

function submit(): void {
    form.put('/home-page', {
        preserveScroll: true,
        // Nilainya ditulis ulang server, jadi kotaknya disemai ulang dari yang
        // TERSIMPAN. Tanpa ini ia memperlihatkan apa yang diketik, bukan apa
        // yang tersimpan — dan selisihnya baru ketahuan pada muat ulang penuh
        // berikutnya, saat orangnya sudah telanjur yakin.
        onSuccess: () => {
            form.defaults({ ...form.data() })
            form.reset()
        },
    })
}
</script>

<template>
    <Head :title="t('home_page.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('home_page.title')"
            :breadcrumbs="[{ label: t('home_page.title') }]"
        >
            <template #description>{{ t('home_page.hint') }}</template>
        </PageHeader>

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit">
            <CardSection :title="t('home_page.hero')">
                <ContextNote>{{ t('home_page.hero_hint') }}</ContextNote>

                <FormRow :label="t('home_page.tagline')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.hero_tagline"
                            :error="form.errors.hero_tagline"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('home_page.headline')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.hero_headline"
                            :error="form.errors.hero_headline"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('home_page.mission')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.hero_mission"
                            textarea
                            :error="form.errors.hero_mission"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('home_page.accountability')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.hero_accountability"
                            textarea
                            :error="form.errors.hero_accountability"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('home_page.primary_cta')"
                    :description="t('home_page.label_hint')"
                    required
                >
                    <div class="flex flex-col gap-3">
                        <AppField
                            v-model="form.hero_primary_cta"
                            :aria-label="t('home_page.primary_cta')"
                            :error="form.errors.hero_primary_cta"
                        />
                        <AppField
                            v-model="form.hero_primary_cta_url"
                            class="font-mono"
                            :aria-label="t('home_page.url_hint')"
                            :error="form.errors.hero_primary_cta_url"
                        />
                        <span class="text-body-xs text-cool-60">{{ t('home_page.url_hint') }}</span>
                    </div>
                </FormRow>

                <FormRow
                    :label="t('home_page.secondary_cta')"
                    :description="t('home_page.label_hint')"
                    required
                >
                    <div class="flex flex-col gap-3">
                        <AppField
                            v-model="form.hero_secondary_cta"
                            :aria-label="t('home_page.secondary_cta')"
                            :error="form.errors.hero_secondary_cta"
                        />
                        <AppField
                            v-model="form.hero_secondary_cta_url"
                            class="font-mono"
                            :aria-label="t('home_page.url_hint')"
                            :error="form.errors.hero_secondary_cta_url"
                        />
                        <span class="text-body-xs text-cool-60">{{ t('home_page.url_hint') }}</span>
                    </div>
                </FormRow>
            </CardSection>

            <CardSection :title="t('home_page.closing')">
                <ContextNote>{{ t('home_page.closing_hint') }}</ContextNote>

                <FormRow
                    :label="t('home_page.headline')"
                    :description="t('home_page.headline_lines_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.closing_headline"
                            textarea
                            :error="form.errors.closing_headline"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('home_page.body')" required>
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.closing_body"
                            textarea
                            :error="form.errors.closing_body"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('home_page.cta')"
                    :description="t('home_page.label_hint')"
                    required
                >
                    <div class="flex flex-col gap-3">
                        <AppField
                            v-model="form.closing_cta"
                            :aria-label="t('home_page.cta')"
                            :error="form.errors.closing_cta"
                        />
                        <AppField
                            v-model="form.closing_cta_url"
                            class="font-mono"
                            :aria-label="t('home_page.url_hint')"
                            :error="form.errors.closing_cta_url"
                        />
                        <span class="text-body-xs text-cool-60">{{ t('home_page.url_hint') }}</span>
                    </div>
                </FormRow>
            </CardSection>

            <CardSection :title="t('home_page.elsewhere')">
                <ContextNote>{{ t('home_page.elsewhere_hint') }}</ContextNote>

                <ul class="flex flex-col border border-cool-20">
                    <li
                        v-for="row in elsewhere"
                        :key="row.key"
                        class="flex items-center justify-between gap-4 border-t border-cool-20 px-4 py-3 first:border-t-0"
                    >
                        <span class="text-body-s text-cool-90">
                            {{ t(`home_page.elsewhere_${row.key}`) }}
                        </span>

                        <AppButton :href="row.href" variant="link" size="s">
                            {{ t('home_page.open') }}
                            <template #iconRight><PhArrowSquareOut :size="16" /></template>
                        </AppButton>
                    </li>
                </ul>
            </CardSection>

            <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
