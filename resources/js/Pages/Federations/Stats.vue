<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { PhPlus, PhTrash } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppField from '@/Components/AppField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

interface Stat {
    label: string
    value: string
    is_active: boolean
}

const props = defineProps<{
    scope: string
    stats: Array<{ id: number; label: string; value: string; isActive: boolean }>
}>()

const { t } = useI18n()

const form = useForm({
    scope: props.scope,
    stats: props.stats.map((s): Stat => ({ label: s.label, value: s.value, is_active: s.isActive })),
})

/**
 * Berpindah lingkup adalah KUNJUNGAN, bukan tab lokal.
 *
 * Tiap lingkup punya barisnya sendiri di database, jadi menukarnya di klien
 * berarti menyimpan sekumpulan baris ke lingkup yang salah kalau seseorang
 * berpindah tab lalu menekan Simpan. `UnsavedGuard` ikut menahannya kalau
 * masih ada perubahan.
 */
function openScope(scope: string): void {
    router.get('/federations/stats', { scope }, { preserveState: false })
}

function addStat(): void {
    form.stats = [...form.stats, { label: '', value: '', is_active: true }]
}

function removeStat(index: number): void {
    form.stats = form.stats.filter((_, i) => i !== index)
}

function submit(): void {
    form.put('/federations/stats', { preserveScroll: true })
}
</script>

<template>
    <Head :title="t('federations.stats_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('federations.stats_title')"
            :breadcrumbs="[
                { label: t('federations.title') },
                { label: t('federations.list'), href: '/federations' },
                { label: t('federations.stats') },
            ]"
        />

        <!-- Dua lingkup, satu layar. Bentuk barisnya identik, jadi dua layar
             terpisah berarti dua tempat yang mengelola hal yang sama. -->
        <div class="flex" role="group" :aria-label="t('federations.stats')">
            <button
                v-for="option in [
                    { value: 'home', label: t('federations.stats_home') },
                    { value: 'members', label: t('federations.stats_members') },
                ]"
                :key="option.value"
                type="button"
                class="cursor-pointer border px-3 py-1.5 text-body-xs transition-colors -ml-px first:ml-0"
                :class="
                    option.value === scope
                        ? 'border-cool-90 bg-cool-90 text-on-inverse'
                        : 'border-cool-30 bg-surface text-cool-70 hover:border-cool-60'
                "
                :aria-pressed="option.value === scope"
                @click="openScope(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <ContextNote>{{ t('federations.stats_hint') }}</ContextNote>

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('federations.stats')">
                <p v-if="form.stats.length === 0" class="text-body-s text-cool-60">
                    {{ t('federations.stats_empty') }}
                </p>

                <div
                    v-for="(stat, index) in form.stats"
                    :key="index"
                    class="flex w-full flex-col gap-3 border-b border-cool-20 pb-4 last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-subtitle-s text-cool-90">#{{ index + 1 }}</span>
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                            :aria-label="t('federations.remove_stat', { name: stat.label || `#${index + 1}` })"
                            @click="removeStat(index)"
                        >
                            <PhTrash :size="16" aria-hidden="true" />
                            {{ t('common.delete') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_160px_auto] sm:items-center">
                        <AppField
                            v-model="stat.label"
                            :aria-label="t('federations.stat_label')"
                            :placeholder="t('federations.stat_label')"
                            :error="(form.errors as any)[`stats.${index}.label`]"
                        />
                        <AppField
                            v-model="stat.value"
                            :aria-label="t('federations.stat_value')"
                            placeholder="120+"
                            :error="(form.errors as any)[`stats.${index}.value`]"
                        />
                        <AppToggle v-model="stat.is_active" :label="t('common.active')" />
                    </div>
                </div>

                <AppButton variant="outline" size="s" @click="addStat">
                    <template #iconLeft><PhPlus :size="20" /></template>
                    {{ t('federations.add_stat') }}
                </AppButton>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/federations" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
