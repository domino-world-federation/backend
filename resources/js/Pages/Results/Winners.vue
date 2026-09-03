<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { PhPlus, PhTrash } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppField from '@/Components/AppField.vue'
import AppButton from '@/Components/AppButton.vue'
import ContextNote from '@/Components/ContextNote.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'
import { formatDate } from '@/utils/format'

interface Winner {
    id?: number
    rank_label: string
    names: string
    country: string
    portraits: File[]
    portraitUrls: string[]
}

const props = defineProps<{
    tournament: { id: number; name: string; location: string; endsOn: string | null }
    winners: Array<{
        id: number
        rank_label: string
        names: string
        country: string
        portraitUrls: string[]
    }>
}>()

const { t } = useI18n()

const form = useForm({
    winners: props.winners.map((w): Winner => ({
        id: w.id,
        rank_label: w.rank_label,
        names: w.names,
        country: w.country,
        portraits: [],
        portraitUrls: w.portraitUrls,
    })),
})

function addWinner(): void {
    form.winners = [
        ...form.winners,
        { id: undefined, rank_label: '', names: '', country: '', portraits: [], portraitUrls: [] },
    ]
}

function removeWinner(index: number): void {
    form.winners = form.winners.filter((_, i) => i !== index)
}

/**
 * Potret dipilih sebagai KUMPULAN, bukan satu per satu.
 *
 * Kartunya menggambar satu lingkaran per pemenang, dan gelar ganda punya dua —
 * jadi kontrolnya `multiple`. Mengunggah mengganti seluruh potret baris itu,
 * bukan menambahkannya, supaya "ganti fotonya" tidak diam-diam berarti
 * "sekarang ada empat".
 */
function pickPortraits(index: number, event: Event): void {
    const input = event.target as HTMLInputElement

    form.winners[index]!.portraits = Array.from(input.files ?? [])
}

function submit(): void {
    form.post(`/results/${props.tournament.id}`, { forceFormData: true, preserveScroll: true })
}
</script>

<template>
    <Head :title="`${t('results.winners_title')} — ${tournament.name}`" />

    <AdminLayout>
        <PageHeader
            :title="tournament.name"
            :breadcrumbs="[
                { label: t('results.title') },
                { label: t('results.list'), href: '/results' },
                { label: t('results.winners_title') },
            ]"
        >
            <template #description>
                {{ tournament.location }} · {{ t('results.ended') }} {{ formatDate(tournament.endsOn) }}
            </template>
        </PageHeader>

        <ContextNote>{{ t('results.winners_hint') }}</ContextNote>

        <form class="flex flex-col items-end gap-4" @submit.prevent="submit">
            <CardSection :title="t('results.winners_title')">
                <p v-if="form.winners.length === 0" class="text-body-s text-cool-60">
                    {{ t('results.winners_empty') }}
                </p>

                <div
                    v-for="(winner, index) in form.winners"
                    :key="index"
                    class="flex w-full flex-col gap-3 border-b border-cool-20 pb-4 last:border-b-0"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-subtitle-s text-cool-90">#{{ index + 1 }}</span>
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                            :aria-label="t('results.remove_winner', { name: winner.names || `#${index + 1}` })"
                            @click="removeWinner(index)"
                        >
                            <PhTrash :size="16" aria-hidden="true" />
                            {{ t('common.delete') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-[160px_1fr_180px]">
                        <AppField
                            v-model="winner.rank_label"
                            :aria-label="t('results.rank_label')"
                            placeholder="CHAMPION"
                            :error="(form.errors as any)[`winners.${index}.rank_label`]"
                        />
                        <AppField
                            v-model="winner.names"
                            :aria-label="t('results.names')"
                            placeholder="Luis Ortega &amp; Mateo Ruiz"
                            :error="(form.errors as any)[`winners.${index}.names`]"
                        />
                        <AppField
                            v-model="winner.country"
                            :aria-label="t('results.country')"
                            :placeholder="t('results.country')"
                            :error="(form.errors as any)[`winners.${index}.country`]"
                        />
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-body-xs text-cool-60">{{ t('results.portraits_hint') }}</label>

                        <!-- Potret yang sudah tersimpan tetap terlihat: tanpa
                             ini, baris yang sudah punya foto terlihat kosong dan
                             orang mengunggah ulang tanpa perlu. -->
                        <div v-if="winner.portraitUrls.length > 0" class="flex flex-wrap gap-2">
                            <img
                                v-for="url in winner.portraitUrls"
                                :key="url"
                                :src="url"
                                :alt="winner.names"
                                class="size-12 rounded-full object-cover"
                            />
                        </div>

                        <input
                            type="file"
                            multiple
                            accept="image/webp"
                            class="text-body-xs text-cool-70"
                            :aria-label="t('results.portraits')"
                            @change="pickPortraits(index, $event)"
                        />

                        <p
                            v-if="(form.errors as any)[`winners.${index}.portraits`]"
                            role="alert"
                            class="text-body-xs text-danger"
                        >
                            {{ (form.errors as any)[`winners.${index}.portraits`] }}
                        </p>
                    </div>
                </div>

                <AppButton variant="outline" size="s" @click="addWinner">
                    <template #iconLeft><PhPlus :size="20" /></template>
                    {{ t('results.add_winner') }}
                </AppButton>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton href="/results" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
