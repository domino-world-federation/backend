<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhPlus, PhTrash } from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import RichTextEditor from '@/Components/Editor/RichTextEditor.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppButton from '@/Components/AppButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { formatDateTime } from '@/utils/format'
import { FADE, RISE, SPRING_SNAP } from '@/motion'

/**
 * Privacy Policy, Terms & Conditions, dan Cookie Policy — Figma `258:8086` /
 * `258:8144`; yang ketiga memakai layar yang sama tanpa wireframe sendiri.
 *
 * Bentuknya identik, jadi satu layar dengan `key` yang berbeda. Isinya bukan
 * satu badan tulisan melainkan DERET BLOK judul+deskripsi yang bisa ditambah,
 * dinonaktifkan satu per satu, dan dihapus — itulah kenapa halaman ini tidak
 * memakai editor teks kaya seperti News.
 */
interface Block {
    id?: number
    title: string
    description: string
    isActive: boolean
}

const props = defineProps<{
    page: {
        key: string
        slug: string
        lastUpdatedAt: string | null
        lastModifiedBy: string | null
        lastModifiedAt: string | null
        blocks: Block[]
    }
}>()

const { t } = useI18n()

/**
 * Namanya dicari lewat KUNCI halamannya, bukan dipilih dengan syarat.
 *
 * Dulu di sini ada `key === 'terms' ? terms : privacy` — dua halaman, satu
 * ternary, benar sampai halaman ketiga lahir. Cookie Policy jatuh ke cabang
 * `else` dan tiap layarnya menulis "Kebijakan Privasi" di judul, breadcrumb,
 * judul kartu, dan label tiap bloknya sekaligus. Tidak ada galat: yang salah
 * cuma namanya. Halaman keempat akan melakukan hal yang sama.
 *
 * Dengan pencarian kunci, halaman yang terjemahannya belum ditulis menampilkan
 * `legal.names.<kunci>` apa adanya — jelek, dan justru itu gunanya: ia tidak
 * bisa disangka nama halaman lain. `LegalPageNamesTest` menahannya lebih dulu.
 */
const pageTitle = computed(() => t(`legal.names.${props.page.key}`))

/** Keadaan formulir sesuai APA YANG TERSIMPAN sekarang. */
function seed() {
    return {
        slug: props.page.slug,
        last_updated_at: props.page.lastUpdatedAt ?? '',
        // Dikirim dengan kunci snake_case karena itu yang divalidasi server.
        blocks: props.page.blocks.map((b) => ({
            title: b.title,
            description: b.description,
            is_active: b.isActive,
        })),
    }
}

const form = useForm(seed())

/**
 * Kunci `v-for` yang bertahan saat baris di tengah dihapus.
 *
 * Indeks TIDAK bisa dipakai: menghapus blok kedua membuat blok ketiga mewarisi
 * kunci `1`, dan Vue menganggapnya "blok kedua yang isinya berubah". Yang
 * dianimasikan keluar jadi baris terakhir, bukan baris yang benar-benar
 * ditekan — dan fokus keyboard ikut melompat.
 */
let nextKey = 0
const keys = new WeakMap<object, number>()

function keyFor(block: object): number {
    if (!keys.has(block)) keys.set(block, nextKey++)

    return keys.get(block)!
}

function addBlock(): void {
    form.blocks = [...form.blocks, { title: '', description: '', is_active: true }]
}

function removeBlock(index: number): void {
    form.blocks = form.blocks.filter((_, i) => i !== index)
}

/**
 * "Discard Changes" mengembalikan formulir ke keadaan tersimpan — ia TIDAK
 * berpindah halaman.
 *
 * Bedanya nyata: tombolnya duduk di sebelah Save, dan yang dimaksud orang saat
 * menekannya adalah "batalkan suntinganku", bukan "bawa aku pergi". Sesudahnya
 * `form.isDirty` kembali `false`, jadi penahan kepergian ikut lepas sendiri.
 */
function discard(): void {
    form.reset()
}

function submit(): void {
    form.put(`/legal-pages/${props.page.key}`, {
        preserveScroll: true,
        onSuccess: () => {
            /*
             * Disemai ulang dari props yang BARU, bukan dibiarkan.
             *
             * Server membuang tag dari deskripsi (`strip_tags`), jadi apa yang
             * tersimpan bisa berbeda dari apa yang barusan diketik. Tanpa baris
             * ini kotaknya tetap memperlihatkan versi ketikan, dan selisihnya
             * baru ketahuan pada muat ulang penuh berikutnya — orangnya sudah
             * telanjur yakin `<b>` yang ia ketik ikut tersimpan.
             */
            form.defaults(seed())
            form.reset()
        },
    })
}

function fieldError(index: number, field: 'title' | 'description'): string | undefined {
    return (form.errors as Record<string, string>)[`blocks.${index}.${field}`]
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <PageHeader
            :title="pageTitle"
            :breadcrumbs="[
                { label: t('settings.site') },
                { label: t('legal.title'), href: '/legal-pages' },
                { label: pageTitle },
            ]"
        >
            <!-- "Last Modified · John Doe · 19/08/2026 03:21 WIB" — siapa yang
                 terakhir menyentuh halaman yang isinya harus tepat secara
                 hukum. Berbeda dari field "Last Updated" di bawah, yang tanggal
                 pilihan redaksi dan tampil di situs publik. -->
            <template v-if="page.lastModifiedAt" #description>
                <span class="inline-flex flex-wrap items-center gap-2">
                    <span>{{ t('legal.last_modified') }}</span>
                    <!-- Nama penyuntingnya dilewati kalau memang belum ada
                         yang menyimpan halaman ini — "Last Modified · — ·"
                         cuma memberi tanda hubung untuk dibaca. -->
                    <template v-if="page.lastModifiedBy">
                        <span class="text-cool-40" aria-hidden="true">·</span>
                        <span class="text-cool-90">{{ page.lastModifiedBy }}</span>
                    </template>
                    <span class="text-cool-40" aria-hidden="true">·</span>
                    <span>{{ formatDateTime(page.lastModifiedAt) }}</span>
                </span>
            </template>
        </PageHeader>

        <form class="flex flex-col items-end gap-6" @submit.prevent="submit">
            <CardSection :title="t('legal.data', { page: pageTitle })">
                <FormRow
                    :label="t('legal.last_updated')"
                    :description="t('legal.last_updated_hint')"
                    required
                >
                    <template #default="{ id }">
                        <AppField
                            :id="id"
                            v-model="form.last_updated_at"
                            type="date"
                            :error="form.errors.last_updated_at"
                        />
                    </template>
                </FormRow>

                <FormRow :label="t('news.field_slug')" :description="t('legal.slug_hint')" required>
                    <template #default="{ id }">
                        <AppField :id="id" v-model="form.slug" :error="form.errors.slug" />
                    </template>
                </FormRow>

                <AnimatePresence>
                    <motion.div
                        v-for="(block, index) in form.blocks"
                        :key="keyFor(block)"
                        layout
                        :initial="{ opacity: 0, y: 8 }"
                        :animate="{ opacity: 1, y: 0 }"
                        :exit="{ opacity: 0, x: -12, transition: FADE }"
                        :transition="RISE"
                    >
                        <FormRow
                            :label="t('legal.block', { page: pageTitle, number: index + 1 })"
                            :description="t('legal.block_hint', { page: pageTitle, number: index + 1 })"
                        >
                            <div class="flex flex-col gap-4">
                                <!-- Sakelar dan tong sampah duduk sebaris dengan
                                     label "Title", di ujung kanan kartu —
                                     keduanya milik SATU blok, dan menaruhnya di
                                     kolom label kiri akan membuatnya terbaca
                                     sebagai milik seluruh halaman. -->
                                <div class="flex items-end justify-between gap-4">
                                    <span class="text-body-s text-cool-70">
                                        {{ t('legal.block_title') }}
                                    </span>

                                    <div class="flex shrink-0 items-center gap-3">
                                        <AppToggle
                                            v-model="block.is_active"
                                            :label="t('legal.block_toggle', { number: index + 1 })"
                                            hide-label
                                        />
                                        <motion.button
                                            type="button"
                                            class="flex size-8 cursor-pointer items-center justify-center text-danger transition-colors hover:text-cool-90"
                                            :aria-label="t('legal.block_delete', { number: index + 1 })"
                                            :while-hover="{ scale: 1.12 }"
                                            :while-press="{ scale: 0.9 }"
                                            :transition="SPRING_SNAP"
                                            @click="removeBlock(index)"
                                        >
                                            <PhTrash :size="20" aria-hidden="true" />
                                        </motion.button>
                                    </div>
                                </div>

                                <AppField
                                    v-model="block.title"
                                    :placeholder="t('legal.block_placeholder')"
                                    :aria-label="t('legal.block_title_of', { number: index + 1 })"
                                    :error="fieldError(index, 'title')"
                                />

                                <span class="text-body-s text-cool-70">
                                    {{ t('legal.block_description') }}
                                </span>

                                <!-- Editor DASAR: tebal, miring, garis bawah,
                                     coret, dan daftar. Tanpa judul, gambar, atau
                                     sorot — bloknya sudah punya judulnya sendiri
                                     di field di atas, dan profil Purifier
                                     `legal` di server menyempit ke daftar yang
                                     sama. Tombol yang ada di sini tapi tidak di
                                     sana akan menghapus pekerjaan penulisnya
                                     saat disimpan. -->
                                <RichTextEditor
                                    v-model="block.description"
                                    variant="basic"
                                    :aria-label="t('legal.block_description_of', { number: index + 1 })"
                                    :error="fieldError(index, 'description')"
                                />
                            </div>
                        </FormRow>
                    </motion.div>
                </AnimatePresence>

                <!-- Sejajar dengan kolom kontrol, bukan dengan kolom label:
                     yang ditambahkannya adalah isi, dan isi hidup di kanan. -->
                <div class="flex">
                    <div class="w-[280px] shrink-0" aria-hidden="true" />
                    <div class="pl-8">
                        <motion.button
                            type="button"
                            class="flex h-10 cursor-pointer items-center gap-2 border-2 border-dashed border-cool-30 bg-surface px-3 text-button-s text-cool-100 transition-colors hover:border-cool-60"
                            :while-hover="{ scale: 1.02 }"
                            :while-press="{ scale: 0.97 }"
                            :transition="SPRING_SNAP"
                            @click="addBlock"
                        >
                            <PhPlus :size="24" aria-hidden="true" />
                            {{ t('common.add_other') }}
                        </motion.button>
                    </div>
                </div>
            </CardSection>

            <div class="flex items-center gap-2">
                <AppButton variant="outline" :disabled="!form.isDirty" @click="discard">
                    {{ t('legal.discard') }}
                </AppButton>
                <AppButton type="submit" :disabled="form.processing">{{ t('common.save') }}</AppButton>
            </div>
        </form>

        <!-- Menahan Cancel, sidebar, Back/Forward, dan penutupan tab selama
             masih ada isian yang belum disimpan. Lihat `UnsavedGuard.vue`. -->
        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
