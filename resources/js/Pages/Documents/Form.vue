<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import type { DocumentCategory } from '@/types'

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
    document: {
        id: number
        title: string
        category: string | null
        status: string
        publishedAt: string | null
        fileName: string
        fileSize: string
    } | null
    categories: DocumentCategory[]
}>()

const { t } = useI18n()

const isEdit = props.document !== null
const title = computed(() => (isEdit ? t('documents.edit') : t('documents.add')))

const form = useForm({
    title: props.document?.title ?? '',
    category: props.document?.category ?? null,
    posting: props.document?.status === 'scheduled' ? 'schedule' : 'now',
    published_at: props.document?.publishedAt ?? '',
    file: null as File | null,
})

const canSchedule = computed(() => form.posting === 'schedule')

const selectedCategory = computed(() => props.categories.find((c) => c.value === form.category))

/**
 * Kalimat di bawah label Category.
 *
 * Tiga keadaan, dan yang ketiga yang paling penting: sebuah kategori bisa
 * menyebut halaman yang belum punya rak dokumen sama sekali (Integrity,
 * Members, About Us). Menyebutnya bersama halaman yang benar-benar
 * menampilkannya akan membuat layar ini berjanji sesuatu yang belum benar —
 * dan yang mengunggah baru tahu setelah membuka halamannya dan tidak menemukan
 * apa-apa. Jadi keduanya diucapkan sebagai dua kalimat yang berbeda.
 */
const categoryHint = computed(() => {
    const selected = selectedCategory.value

    if (!selected) return t('documents.category_hint')

    const appears = t('documents.category_appears', { pages: selected.pages.join(', ') })

    return selected.planned.length === 0
        ? appears
        : `${appears} ${t('documents.category_planned', { pages: selected.planned.join(', ') })}`
})

/*
 * Jadwal dipecah jadi jam dan tanggal — dua kontrol seperti di desain
 * (`262:3449`), tapi SATU `published_at` yang dikirim. Alasannya sama dengan
 * Add News dan Add Gallery: dua field di server berarti dua nilai yang bisa
 * saling bertentangan.
 */
const initial = props.document?.publishedAt ?? ''
const scheduleDate = ref(initial.slice(0, 10))
const scheduleTime = ref(initial.slice(11, 16))

watch([scheduleDate, scheduleTime], ([date, time]) => {
    form.published_at = date && time ? `${date}T${time}` : ''
})

function submit(): void {
    const options = { forceFormData: true }

    if (isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/documents/${props.document!.id}`,
            options,
        )
        return
    }

    form.post('/documents', options)
}
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <PageHeader
            :title="title"
            :breadcrumbs="[
                { label: t('documents.title'), href: '/documents' },
                { label: t('documents.list'), href: '/documents' },
                { label: title },
            ]"
        />

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit">
            <CardSection :title="t('documents.data')">
                <FormRow
                    :label="t('documents.doc_title')"
                    :description="t('documents.doc_title_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.title"
                            :error="form.errors.title"
                            autofocus
                        />
                    </template>
                </FormRow>

                <FormRow
                    :label="t('documents.file')"
                    :description="t('documents.file_hint')"
                    :required="!isEdit"
                    compact
                >
                    <template #default="{ id }">
                        <MediaUpload
                            :id="id"
                            v-model="form.file"
                            kind="document"
                            :existing-label="document ? `${document.fileName} · ${document.fileSize}` : null"
                            :error="form.errors.file"
                        />
                    </template>
                </FormRow>

                <!-- Keterangan di bawah label ikut BERUBAH saat kategorinya
                     dipilih, dan itu yang membuat kolom ini bisa dijawab.
                     Sebelumnya ia berbunyi "Select category of document." —
                     kalimat yang mengulang nama kolomnya dan tidak memberi tahu
                     satu pun akibat dari memilih. Yang ingin diketahui orang
                     yang sedang mengunggah adalah di halaman mana berkasnya akan
                     muncul, dan itu pertanyaan yang jawabannya cuma ada di
                     config/dwf.php. -->
                <FormRow
                    :label="t('common.category')"
                    :description="categoryHint"
                >
                    <template #default="{ id }">
                        <SelectField
                            :id="id"
                            v-model="form.category"
                            :options="categories"
                            :placeholder="t('documents.select_category')"
                            :error="form.errors.category"
                        />
                    </template>
                </FormRow>

                <!-- Menggantikan sakelar Status. Desain `262:3449` menulis
                     "Publish Time: Now / Schedule" — pertanyaan yang sama
                     dengan News dan Gallery, jadi kontrolnya juga sama.
                     Perhatikan yang TIDAK ada di sini: tombol Save Draft.
                     Layar ini cuma punya Cancel dan Save, jadi draft hanya
                     dicapai lewat pemilih Visibility di daftar. -->
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
                                name="document-posting"
                                :label="t('news.posting_now')"
                            />
                            <AppRadio
                                v-model="form.posting"
                                value="schedule"
                                name="document-posting"
                                :label="t('news.posting_schedule')"
                            />
                        </div>

                        <!-- Jam dulu, tanggal sesudahnya — urutan desain. -->
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

            <div class="flex items-center gap-2">
                <AppButton href="/documents" variant="outline">{{ t('common.cancel') }}</AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>
        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
