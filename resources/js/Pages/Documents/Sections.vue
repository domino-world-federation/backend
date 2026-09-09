<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhX } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ContextNote from '@/Components/ContextNote.vue'
import ReorderList from '@/Components/ReorderList.vue'
import SelectField from '@/Components/SelectField.vue'
import AppButton from '@/Components/AppButton.vue'
import IconButton from '@/Components/IconButton.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * "Documents per Halaman" — memilih DAN mengurutkan isi tiap rak publik.
 *
 * Kembaran `Faq/Pages.vue`, sampai ke bentuk kartunya. Masalahnya memang
 * masalah yang sama, dan dua layar yang menyelesaikan masalah yang sama dengan
 * tata letak yang berbeda adalah dua layar yang harus dipelajari dua kali.
 *
 * Tiga hal yang TIDAK ada di layar FAQ, dan sebabnya:
 *
 *   1. Tiap rak punya perpustakaannya SENDIRI, disaring ke kategorinya. Satu
 *      daftar bersama akan membiarkan orang menaruh rulebook di rak publikasi.
 *   2. Rak yang belum disentuh menyebut dirinya otomatis. Tanpa itu, rak yang
 *      di situs publik penuh tampil kosong di sini, dan yang membacanya
 *      menyimpulkan fiturnya rusak.
 *   3. Batasnya berbeda-beda (1 untuk Official Rulebook, 6 untuk grid), jadi
 *      penghitungnya membaca `max` tiap rak, bukan satu angka bersama.
 *
 * Simpannya PER KARTU. Satu tombol untuk sembilan rak akan membuat penolakan di
 * satu rak membatalkan delapan rak lain yang tidak salah apa-apa.
 */
interface Row {
    id: number
    label: string
    note?: string | null
    isLive: boolean
}

interface SectionData {
    key: string
    page: string
    label: string
    category: string | null
    max: number
    isAuto: boolean
    documents: Row[]
    library: Array<{ value: number; label: string; isLive: boolean }>
}

const props = defineProps<{ sections: SectionData[] }>()

const { t } = useI18n()

/** Isi tiap rak, disimpan lokal sampai kartunya ditekan Simpan. */
const rows = ref<Record<string, Row[]>>(
    Object.fromEntries(props.sections.map((s) => [s.key, [...s.documents]])),
)

/** Salinan awal — pembanding untuk "ada yang belum disimpan". */
const initial = Object.fromEntries(props.sections.map((s) => [s.key, s.documents.map((d) => d.id)]))

const picked = ref<Record<string, number | null>>(
    Object.fromEntries(props.sections.map((s) => [s.key, null])),
)

const saving = ref<string | null>(null)

/** Katalog rata per rak: id → label + status tayangnya. */
const catalogue = computed(() => {
    const map = new Map<string, Map<number, { label: string; isLive: boolean }>>()

    for (const section of props.sections) {
        map.set(
            section.key,
            new Map(section.library.map((o) => [o.value, { label: o.label, isLive: o.isLive }])),
        )
    }

    return map
})

/** Yang belum dipasang di rak ini — sisanya tidak perlu ditawarkan lagi. */
function choicesFor(section: SectionData) {
    const taken = new Set(rows.value[section.key]?.map((r) => r.id) ?? [])

    return section.library
        .filter((o) => !taken.has(o.value))
        .map((o) => ({ value: o.value, label: o.label }))
}

function isFull(section: SectionData): boolean {
    return (rows.value[section.key]?.length ?? 0) >= section.max
}

function isDirty(key: string): boolean {
    const current = rows.value[key]?.map((r) => r.id) ?? []
    const before = initial[key] ?? []

    return current.length !== before.length || current.some((id, i) => id !== before[i])
}

const anyDirty = computed(() => props.sections.some((s) => isDirty(s.key)))

/**
 * Placeholder yang menyebut SEBABNYA kotaknya kosong.
 *
 * "Belum ada dokumen berkategori ini" dan "semuanya sudah dipasang" adalah dua
 * keadaan yang menuntut tindakan berbeda — yang pertama unggah, yang kedua
 * tidak ada.
 */
function placeholderFor(section: SectionData): string {
    if (section.library.length === 0) return t('documents.sections_empty_library')
    if (choicesFor(section).length === 0) return t('documents.sections_none_left')

    return t('documents.sections_add')
}

function add(section: SectionData): void {
    const id = picked.value[section.key]
    const entry = id === null ? undefined : catalogue.value.get(section.key)?.get(id)

    if (id === null || !entry || isFull(section)) return

    rows.value[section.key] = [
        ...(rows.value[section.key] ?? []),
        { id, label: entry.label, note: section.category, isLive: entry.isLive },
    ]
    picked.value[section.key] = null
}

function remove(key: string, id: number): void {
    rows.value[key] = (rows.value[key] ?? []).filter((r) => r.id !== id)
}

function reorder(key: string, ids: number[]): void {
    const byId = new Map((rows.value[key] ?? []).map((r) => [r.id, r]))
    rows.value[key] = ids.map((id) => byId.get(id)).filter((r): r is Row => r !== undefined)
}

function save(key: string): void {
    saving.value = key
    router.put(
        '/documents/sections',
        { section: key, ids: rows.value[key]?.map((r) => r.id) ?? [] },
        {
            preserveScroll: true,
            onSuccess: () => {
                initial[key] = rows.value[key]?.map((r) => r.id) ?? []
            },
            onFinish: () => (saving.value = null),
        },
    )
}
</script>

<template>
    <Head :title="t('documents.sections_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('documents.sections_title')"
            :breadcrumbs="[
                { label: t('documents.title'), href: '/documents' },
                { label: t('documents.list'), href: '/documents' },
                { label: t('documents.sections_title') },
            ]"
        >
            <template #description>{{ t('documents.sections_hint') }}</template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <CardSection
                v-for="section in sections"
                :key="section.key"
                :title="`${section.page} — ${section.label}`"
            >
                <template #header>
                    <span class="text-body-s text-cool-60">
                        {{
                            t('documents.sections_count', {
                                used: rows[section.key]?.length ?? 0,
                                max: section.max,
                            })
                        }}
                    </span>
                </template>

                <!-- Kategori raknya ditulis, bukan cuma ditegakkan diam-diam:
                     picker yang menawarkan tiga berkas dari perpustakaan berisi
                     tiga puluh menimbulkan pertanyaan yang jawabannya ada di
                     baris ini. -->
                <p class="text-body-s text-cool-60">
                    {{
                        section.category
                            ? t('documents.sections_category', { category: section.category })
                            : t('documents.sections_all_categories')
                    }}
                </p>

                <ContextNote v-if="section.isAuto && (rows[section.key]?.length ?? 0) === 0">
                    {{ t('documents.sections_auto') }}
                </ContextNote>

                <ContextNote v-if="(rows[section.key] ?? []).some((r) => !r.isLive)" tone="warning">
                    {{ t('documents.sections_not_live_hint') }}
                </ContextNote>

                <ReorderList
                    :items="
                        (rows[section.key] ?? []).map((r) => ({
                            id: r.id,
                            label: r.label,
                            note: r.isLive
                                ? r.note
                                : `${r.note ?? ''} · ${t('documents.sections_not_live')}`,
                        }))
                    "
                    @change="(ids) => reorder(section.key, ids)"
                >
                    <template #rowActions="{ row }">
                        <IconButton
                            :label="t('documents.sections_remove', { name: row.label })"
                            tone="danger"
                            @click="remove(section.key, row.id)"
                        >
                            <PhX :size="16" aria-hidden="true" />
                        </IconButton>
                    </template>
                </ReorderList>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <span class="min-w-0 flex-1">
                        <SelectField
                            v-model="picked[section.key]"
                            :options="choicesFor(section)"
                            :placeholder="placeholderFor(section)"
                            :disabled="isFull(section) || choicesFor(section).length === 0"
                        />
                    </span>

                    <!-- Mati DENGAN alasannya, bukan cuma mati: server yang
                         menolak saat ditekan memberi tahu terlambat. -->
                    <AppButton
                        variant="outline"
                        :disabled="isFull(section) || picked[section.key] === null"
                        :title="
                            isFull(section)
                                ? t('documents.sections_full', { max: section.max })
                                : undefined
                        "
                        @click="add(section)"
                    >
                        {{ t('documents.sections_add_action') }}
                    </AppButton>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <AppButton
                        :disabled="!isDirty(section.key) || saving === section.key"
                        @click="save(section.key)"
                    >
                        {{ t('common.save_order') }}
                    </AppButton>
                </div>
            </CardSection>
        </div>

        <!-- Pilihan yang sudah diseret tapi belum disimpan sama saja dengan
             isian formulir yang belum disimpan: hilang tanpa jejak. -->
        <UnsavedGuard :dirty="anyDirty" />
    </AdminLayout>
</template>
