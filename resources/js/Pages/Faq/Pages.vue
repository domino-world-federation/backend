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
 * "FAQ per Halaman" — memilih DAN mengurutkan isi tiap halaman publik.
 *
 * Ketiga halaman berdiri berdampingan di satu layar, bukan satu per satu di
 * balik pemilih. Itu jawaban langsung atas bug yang melahirkan layar ini: dulu
 * ketiganya berbagi satu urutan, dan yang membuatnya tidak pernah ketahuan
 * adalah tidak adanya satu pun tempat yang memperlihatkan ketiganya sekaligus.
 *
 * Simpannya PER KARTU. Satu tombol untuk ketiganya akan membuat penolakan di
 * satu halaman membatalkan dua halaman lain yang tidak salah apa-apa.
 */
interface Row {
    id: number
    label: string
    note?: string | null
    isActive: boolean
}

interface PageData {
    key: string
    label: string
    max: number
    faqs: Row[]
}

const props = defineProps<{
    pages: PageData[]
    library: Array<{ label: string; options: Array<{ value: number; label: string }> }>
}>()

const { t } = useI18n()

/** Isi tiap halaman, disimpan lokal sampai kartunya ditekan Simpan. */
const rows = ref<Record<string, Row[]>>(
    Object.fromEntries(props.pages.map((p) => [p.key, [...p.faqs]])),
)

/** Salinan awal — pembanding untuk "ada yang belum disimpan". */
const initial = Object.fromEntries(props.pages.map((p) => [p.key, p.faqs.map((f) => f.id)]))

const picked = ref<Record<string, number | null>>(
    Object.fromEntries(props.pages.map((p) => [p.key, null])),
)

const saving = ref<string | null>(null)

/**
 * Katalog rata: id → label + kategorinya.
 *
 * Kategori diambil dari NAMA KELOMPOK, bukan dikirim ulang per pilihan — di
 * `<optgroup>` keduanya memang hal yang sama.
 */
const catalogue = computed(() => {
    const map = new Map<number, { label: string; note: string }>()

    for (const group of props.library) {
        for (const option of group.options) {
            map.set(option.value, { label: option.label, note: group.label })
        }
    }

    return map
})

/** Yang belum dipasang di halaman ini — sisanya tidak perlu ditawarkan lagi. */
function choicesFor(page: string) {
    const taken = new Set(rows.value[page]?.map((r) => r.id) ?? [])

    return props.library
        .map((group) => ({
            label: group.label,
            options: group.options.filter((o) => !taken.has(o.value)),
        }))
        .filter((group) => group.options.length > 0)
}

function isFull(page: PageData): boolean {
    return (rows.value[page.key]?.length ?? 0) >= page.max
}

function isDirty(page: string): boolean {
    const current = rows.value[page]?.map((r) => r.id) ?? []
    const before = initial[page] ?? []

    return current.length !== before.length || current.some((id, i) => id !== before[i])
}

const anyDirty = computed(() => props.pages.some((p) => isDirty(p.key)))

function add(page: PageData): void {
    const id = picked.value[page.key]
    const entry = id === null ? undefined : catalogue.value.get(id)

    if (id === null || !entry || isFull(page)) return

    rows.value[page.key] = [
        ...(rows.value[page.key] ?? []),
        { id, label: entry.label, note: entry.note, isActive: true },
    ]
    picked.value[page.key] = null
}

function remove(page: string, id: number): void {
    rows.value[page] = (rows.value[page] ?? []).filter((r) => r.id !== id)
}

function reorder(page: string, ids: number[]): void {
    const byId = new Map((rows.value[page] ?? []).map((r) => [r.id, r]))
    rows.value[page] = ids.map((id) => byId.get(id)).filter((r): r is Row => r !== undefined)
}

function save(page: string): void {
    saving.value = page
    router.put(
        '/faq/pages',
        { page, ids: rows.value[page]?.map((r) => r.id) ?? [] },
        {
            preserveScroll: true,
            onSuccess: () => {
                initial[page] = rows.value[page]?.map((r) => r.id) ?? []
            },
            onFinish: () => (saving.value = null),
        },
    )
}
</script>

<template>
    <Head :title="t('faq.pages_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('faq.pages_title')"
            :breadcrumbs="[
                { label: t('faq.title'), href: '/faq' },
                { label: t('faq.list'), href: '/faq' },
                { label: t('faq.pages_title') },
            ]"
        >
            <template #description>{{ t('faq.pages_hint') }}</template>
        </PageHeader>

        <div class="flex flex-col gap-6">
            <CardSection v-for="page in pages" :key="page.key" :title="page.label">
                <template #header>
                    <span class="text-body-s text-cool-60">
                        {{ t('faq.pages_count', { used: rows[page.key]?.length ?? 0, max: page.max }) }}
                    </span>
                </template>

                <!-- Pertanyaan nonaktif TETAP digambar, ditandai. Membuangnya
                     diam-diam berarti orang melihat dua baris, menambah yang
                     ketiga, lalu ditolak karena halamannya penuh oleh sesuatu
                     yang tidak ada di layar. -->
                <ContextNote v-if="(rows[page.key] ?? []).some((r) => !r.isActive)" tone="warning">
                    {{ t('faq.pages_inactive_hint') }}
                </ContextNote>

                <ReorderList
                    :items="
                        (rows[page.key] ?? []).map((r) => ({
                            id: r.id,
                            label: r.label,
                            note: r.isActive ? r.note : `${r.note ?? ''} · ${t('faq.pages_inactive')}`,
                        }))
                    "
                    @change="(ids) => reorder(page.key, ids)"
                >
                    <template #rowActions="{ row }">
                        <IconButton
                            :label="t('faq.pages_remove', { name: row.label })"
                            tone="danger"
                            @click="remove(page.key, row.id)"
                        >
                            <PhX :size="16" aria-hidden="true" />
                        </IconButton>
                    </template>
                </ReorderList>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-start">
                    <span class="min-w-0 flex-1">
                        <SelectField
                            v-model="picked[page.key]"
                            :groups="choicesFor(page.key)"
                            :placeholder="
                                choicesFor(page.key).length === 0
                                    ? t('faq.pages_none_left')
                                    : t('faq.pages_add')
                            "
                            :disabled="isFull(page) || choicesFor(page.key).length === 0"
                        />
                    </span>

                    <!-- Mati DENGAN alasannya, bukan cuma mati: server yang
                         menolak saat ditekan memberi tahu terlambat. -->
                    <AppButton
                        variant="outline"
                        :disabled="isFull(page) || picked[page.key] === null"
                        :title="isFull(page) ? t('faq.pages_full', { max: page.max }) : undefined"
                        @click="add(page)"
                    >
                        {{ t('faq.pages_add_action') }}
                    </AppButton>
                </div>

                <div class="flex items-center justify-end gap-2">
                    <AppButton
                        :disabled="!isDirty(page.key) || saving === page.key"
                        @click="save(page.key)"
                    >
                        {{ t('common.save_order') }}
                    </AppButton>
                </div>
            </CardSection>
        </div>

        <!-- Urutan yang sudah diseret tapi belum disimpan sama saja dengan
             isian formulir yang belum disimpan: hilang tanpa jejak. -->
        <UnsavedGuard :dirty="anyDirty" />
    </AdminLayout>
</template>
