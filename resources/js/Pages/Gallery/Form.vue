<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import SelectField from '@/Components/SelectField.vue'
import AppRadio from '@/Components/AppRadio.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import MediaUpload from '@/Components/MediaUpload.vue'

const props = defineProps<{
    item: {
        id: number
        kind: string
        alt: string | null
        eventId: number
        eventType: string | null
        slug: string
        status: string
        publishedAt: string | null
        url: string | null
    } | null
    events: Array<{ value: number; label: string; type: string }>
}>()

const { t } = useI18n()

const isEdit = props.item !== null
const pageTitle = computed(() => (isEdit ? t('gallery.edit') : t('gallery.add')))

const form = useForm({
    type: props.item?.eventType ?? 'event',
    event_mode: isEdit ? 'existing' : 'new',
    gallery_event_id: props.item?.eventId ?? null,
    event_name: '',
    kind: props.item?.kind ?? 'image',
    alt: props.item?.alt ?? '',
    slug: props.item?.slug ?? '',
    posting: props.item?.status === 'scheduled' ? 'schedule' : 'now',
    published_at: props.item?.publishedAt ?? '',
    asset: null as File | null,
})

const canSchedule = computed(() => form.posting === 'schedule')

/*
 * Jadwal dipecah jadi jam dan tanggal — dua kontrol, seperti di desain, tapi
 * SATU `published_at` yang dikirim. Alasannya sama seperti di Add News: dua
 * field di server berarti dua nilai yang bisa saling bertentangan.
 */
const initial = props.item?.publishedAt ?? ''
const scheduleDate = ref(initial.slice(0, 10))
const scheduleTime = ref(initial.slice(11, 16))

watch([scheduleDate, scheduleTime], ([date, time]) => {
    form.published_at = date && time ? `${date}T${time}` : ''
})

/** Hanya event bertipe sama yang boleh dipilih — Event dan Tournament tidak
 *  bercampur di satu daftar. */
const eventOptions = computed(() =>
    props.events
        .filter((event) => event.type === form.type)
        .map((event) => ({ value: event.value, label: event.label })),
)

function submit(posting: 'now' | 'schedule' | 'draft'): void {
    // "Save Draft" menimpa pilihan Publish Time; "Publish" menghormatinya,
    // jadi menekan Publish saat Schedule terpilih tetap menjadwalkan.
    form.posting = posting === 'draft' ? 'draft' : form.posting === 'schedule' ? 'schedule' : 'now'
    if (posting === 'draft') form.posting = 'draft'

    const options = { forceFormData: true }

    if (isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/gallery/${props.item!.id}`, options)
        return
    }

    form.post('/gallery', options)
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <PageHeader
            :title="pageTitle"
            :breadcrumbs="[
                { label: t('gallery.title'), href: '/gallery' },
                { label: t('gallery.list'), href: '/gallery' },
                { label: pageTitle },
            ]"
        />

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit('now')">
            <CardSection :title="t('gallery.data')">
                <FormRow :label="t('gallery.select_type')" required compact>
                    <div class="flex items-center gap-6">
                        <AppRadio v-model="form.type" value="event" name="gallery-type" :label="t('gallery.type_event')" />
                        <AppRadio
                            v-model="form.type"
                            value="tournament"
                            name="gallery-type"
                            :label="t('gallery.type_tournament')"
                        />
                    </div>
                </FormRow>

                <!-- Labelnya mengikuti jenis yang dipilih. Desain `478:6930`
                     MEMBUANG baris ini seluruhnya saat Tournament dipilih dan
                     hanya menyisakan satu dropdown — itu berarti turnamen baru
                     tidak akan pernah bisa dibuat dari layar ini. Barisnya
                     dipertahankan; yang mengikuti desain adalah kata-katanya. -->
                <FormRow
                    :label="form.type === 'tournament' ? t('gallery.tournament_name') : t('gallery.event_name')"
                    :description="t('gallery.event_name_hint')"
                    required
                >
                    <template #default="{ id }">
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-6">
                                <AppRadio
                                    v-model="form.event_mode"
                                    value="new"
                                    name="event-mode"
                                    :label="t('gallery.event_new')"
                                />
                                <AppRadio
                                    v-model="form.event_mode"
                                    value="existing"
                                    name="event-mode"
                                    :label="t('gallery.event_existing')"
                                />
                            </div>

                            <AppField
                                v-if="form.event_mode === 'new'"
                                :id="id"
                                v-model="form.event_name"
                                :placeholder="t('gallery.event_new_placeholder')"
                                :error="form.errors.event_name"
                            />
                            <SelectField
                                v-else
                                :id="id"
                                v-model="form.gallery_event_id"
                                :options="eventOptions"
                                :placeholder="t('gallery.select_event')"
                                :error="form.errors.gallery_event_id"
                            />
                        </div>
                    </template>
                </FormRow>

                <FormRow
                    :label="t('gallery.asset')"
                    :description="t('gallery.asset_hint')"
                    :required="!isEdit"
                    compact
                >
                    <template #default="{ id }">
                        <div class="flex flex-col gap-4">
                            <div class="flex items-center gap-6">
                                <AppRadio
                                    v-model="form.kind"
                                    value="image"
                                    name="asset-kind"
                                    :label="t('gallery.kind_image')"
                                />
                                <AppRadio
                                    v-model="form.kind"
                                    value="video"
                                    name="asset-kind"
                                    :label="t('gallery.kind_video')"
                                />
                            </div>

                            <MediaUpload
                                :id="id"
                                v-model="form.asset"
                                :kind="form.kind === 'video' ? 'video' : 'image'"
                                :existing-url="item?.url"
                                :error="form.errors.asset"
                            />
                        </div>
                    </template>
                </FormRow>

                <FormRow
                    :label="t('gallery.alt')"
                    :description="t('gallery.alt_hint')"
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.alt" :error="form.errors.alt" />
                    </template>
                </FormRow>

                <FormRow :label="t('news.field_slug')" :description="t('news.field_slug_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.slug" :error="form.errors.slug" />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('news.posting_time')"
                    :description="t('news.posting_time_hint')"
                    compact
                >
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-6">
                            <AppRadio
                                v-model="form.posting"
                                value="now"
                                name="gallery-posting"
                                :label="t('news.posting_now')"
                            />
                            <AppRadio
                                v-model="form.posting"
                                value="schedule"
                                name="gallery-posting"
                                :label="t('news.posting_schedule')"
                            />
                        </div>

                        <!-- Jam dulu, tanggal sesudahnya — urutannya mengikuti
                             desain, sama dengan Add News. -->
                        <div v-if="canSchedule" class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2">
                            <AppField
                                v-model="scheduleTime"
                                type="time"
                                :aria-label="t('news.schedule_time')"
                                :error="form.errors.published_at"
                            />
                            <AppField
                                v-model="scheduleDate"
                                type="date"
                                :aria-label="t('news.schedule_date')"
                            />
                        </div>
                    </div>
                </FormRow>
            </CardSection>

            <div class="flex flex-wrap items-center gap-2">
                <AppButton variant="outline" :disabled="form.processing" @click="submit('draft')">
                    {{ t('common.save_draft') }}
                </AppButton>
                <AppButton href="/gallery" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('news.posting') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
