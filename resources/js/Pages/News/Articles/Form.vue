<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import SelectField from '@/Components/SelectField.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppRadio from '@/Components/AppRadio.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import RichTextEditor from '@/Components/Editor/RichTextEditor.vue'
import type { SharedProps } from '@/types'

interface Article {
    id: number
    title: string
    slug: string
    excerpt: string | null
    body: string | null
    categoryId: number
    isHighlighted: boolean
    status: string
    publishedAt: string | null
    authorName: string | null
    createdAt: string | null
    heroUrl: string | null
    landscapeUrl: string | null
}

const props = defineProps<{
    article: Article | null
    categories: Array<{ value: number; label: string }>
}>()

const { t } = useI18n()

const isEdit = props.article !== null
const title = computed(() => (isEdit ? t('news.edit') : t('news.add')))

const form = useForm({
    title: props.article?.title ?? '',
    slug: props.article?.slug ?? '',
    news_category_id: props.article?.categoryId ?? null,
    excerpt: props.article?.excerpt ?? '',
    body: props.article?.body ?? '',
    is_highlighted: props.article?.isHighlighted ?? false,
    posting: props.article?.status === 'scheduled' ? 'schedule' : 'now',
    published_at: props.article?.publishedAt ?? '',
    hero: null as File | null,
    landscape: null as File | null,
})

// Slug mengikuti judul selama editor belum menyentuhnya sendiri. Sekali diubah
// tangan, ia berhenti mengikuti — kalau tidak, memperbaiki typo di judul
// diam-diam memutus URL yang sudah dibagikan orang.
const slugTouched = ref(isEdit)

watch(
    () => form.title,
    (title) => {
        if (slugTouched.value) return
        form.slug = title
            .toLowerCase()
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
    },
)

const canSchedule = computed(() => form.posting === 'schedule')

/**
 * Penulisnya digambar sebagai field MATI, bukan sebagai paragraf.
 *
 * Nilainya memang tidak bisa diubah, tapi ia berdiri di deret yang sama dengan
 * Title, Category, dan Slug — dan satu baris yang bentuknya berbeda dari
 * tetangganya terbaca sebagai "yang ini rusak", bukan "yang ini terkunci".
 *
 * Artikel baru belum punya penulis di database, jadi yang ditampilkan akun yang
 * sedang membuka — persis siapa yang akan tercatat begitu Simpan ditekan.
 */
const page = usePage<SharedProps>()
const authorName = computed(() => props.article?.authorName ?? page.props.auth.user?.name ?? '')

/*
 * Jadwal dipecah jadi jam dan tanggal — dua kontrol, seperti di desain.
 *
 * Yang dikirim ke server tetap SATU `published_at`. Memecahnya juga di sisi
 * server berarti dua field yang bisa saling bertentangan (tanggal terisi, jam
 * kosong) dan aturan validasi yang harus menjelaskan gabungannya; di sini
 * gabungannya cuma satu baris.
 *
 * Keduanya `<input type="time">` dan `type="date"` sungguhan, bukan dropdown
 * yang digambar mirip: pemilih bawaan sistem sudah tahu format tanggal lokal,
 * bisa dipakai dengan keyboard, dan tidak perlu daftar 1.440 menit.
 */
const initial = props.article?.publishedAt ?? ''
const scheduleDate = ref(initial.slice(0, 10))
const scheduleTime = ref(initial.slice(11, 16))

watch([scheduleDate, scheduleTime], ([date, time]) => {
    form.published_at = date && time ? `${date}T${time}` : ''
})

function submit(posting: 'now' | 'schedule' | 'draft'): void {
    form.posting = posting === 'draft' ? 'draft' : form.posting === 'schedule' ? 'schedule' : 'now'
    if (posting === 'draft') form.posting = 'draft'

    const options = { forceFormData: true }

    if (isEdit) {
        // Laravel tidak membaca berkas dari request PUT. Inertia mengirimnya
        // sebagai POST dengan `_method`, dan `form.post` di bawah inilah yang
        // membuat unggahan gambar tetap sampai saat menyunting.
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/news/${props.article!.id}`, options)
        return
    }

    form.post('/news', options)
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('news.title'), href: '/news' },
                { label: t('news.list'), href: '/news' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit('now')">
            <CardSection :title="t('news.data')">
                <FormRow
                    :label="t('news.image_hero')"
                    :description="t('news.image_hint_hero')"
                    :required="!isEdit"
                    compact
                >
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.hero"
                            :existing-url="article?.heroUrl"
                            :error="form.errors.hero"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('news.image_landscape')"
                    :description="t('news.image_hint_landscape')"
                    :required="!isEdit"
                    compact
                >
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.landscape"
                            :existing-url="article?.landscapeUrl"
                            :error="form.errors.landscape"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('news.field_title')"
                    :description="t('news.field_title_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.title" :error="form.errors.title" />
                    </template>
                </FormRow>

                <FormRow :label="t('common.category')" :description="t('news.field_category_hint')" required>
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.news_category_id"
                            :options="categories"
                            :placeholder="t('gallery.select_event')"
                            :error="form.errors.news_category_id"
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('news.field_slug')"
                    :description="t('news.field_slug_hint')"
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.slug"
                            :error="form.errors.slug"
                            @input="slugTouched = true"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('news.field_content')" :description="t('news.field_content_hint')" required>
                    <template #default="{ id }">
                        <RichTextEditor :id="id" v-model="form.body" :error="form.errors.body" />
                    </template>
                </FormRow>

                <!-- Garis pemisah: di desain, blok "siapa dan kapan" berdiri
                     terpisah dari blok isi. -->
                <hr class="border-cool-20" />

                <FormRow :label="t('news.author')" :description="t('news.author_hint')">
                    <template #default="{ id }">
                        <AppField :id="id" :model-value="authorName" disabled />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('news.highlight')"
                    :description="t('news.highlight_hint')"
                    compact
                >
                    <AppToggle v-model="form.is_highlighted" :label="t('common.active')" />
                </FormRow>

                <FormRow :label="t('news.posting_time')" :description="t('news.posting_time_hint')" compact>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-6">
                            <AppRadio v-model="form.posting" value="now" name="posting" :label="t('news.posting_now')" />
                            <AppRadio
                                v-model="form.posting"
                                value="schedule"
                                name="posting"
                                :label="t('news.posting_schedule')"
                            />
                        </div>

                        <!-- Jam DULU, tanggal sesudahnya — urutannya mengikuti
                             desain. -->
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
                <AppButton href="/news" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('news.posting') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
