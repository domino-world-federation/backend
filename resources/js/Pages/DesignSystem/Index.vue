<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import {
    PhDotsThreeOutline,
    PhMagnifyingGlass,
    PhPencilSimple,
    PhPlus,
    PhTrash,
    PhX,
} from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import ContextNote from '@/Components/ContextNote.vue'
import ProgressSteps from '@/Components/ProgressSteps.vue'
import DemoBlock from '@/Components/DemoBlock.vue'
import CodeBlock from '@/Components/CodeBlock.vue'

import AppButton from '@/Components/AppButton.vue'
import AppField from '@/Components/AppField.vue'
import FormRow from '@/Components/FormRow.vue'
import AppToggle from '@/Components/AppToggle.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import AppRadio from '@/Components/AppRadio.vue'
import DataTable from '@/Components/DataTable.vue'
import Skeleton from '@/Components/Skeleton.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'
import AppPagination from '@/Components/AppPagination.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import SelectField from '@/Components/SelectField.vue'
import ReorderList from '@/Components/ReorderList.vue'
import IconButton from '@/Components/IconButton.vue'
import RichTextEditor from '@/Components/Editor/RichTextEditor.vue'

import * as snippet from '@/Pages/DesignSystem/snippets'
import type { TableColumn } from '@/types'

/**
 * Design System — satu halaman berisi seluruh komponen bersama, contoh
 * hidupnya, dan kode yang bisa disalin.
 *
 * Bukan bagian dari wireframe: ini alat kerja, bukan layar produk. Ada karena
 * 18 layar CMS yang menyusul memakai komponen yang sama berulang-ulang, dan
 * satu tempat untuk melihat "seperti apa bentuknya dan bagaimana memanggilnya"
 * lebih murah daripada membuka tiga file tiap kali.
 */

const SECTIONS = [
    { id: 'mulai', label: 'Mulai dari sini' },
    { id: 'tipografi', label: 'Tipografi' },
    { id: 'warna', label: 'Warna' },
    { id: 'ikon', label: 'Ikon' },
    { id: 'tombol', label: 'Tombol' },
    { id: 'form', label: 'Field & baris form' },
    { id: 'kontrol', label: 'Toggle, checkbox, radio' },
    { id: 'pilihan', label: 'Pilihan (select)' },
    { id: 'editor', label: 'Editor teks kaya' },
    { id: 'urutan', label: 'Daftar bisa diurutkan' },
    { id: 'tabel', label: 'Tabel' },
    { id: 'pagination', label: 'Pagination' },
    { id: 'navigasi', label: 'Breadcrumb & kepala halaman' },
    { id: 'kartu', label: 'Kartu' },
    { id: 'catatan', label: 'Catatan konteks' },
    { id: 'progres', label: 'Progress step' },
    { id: 'dialog', label: 'Dialog konfirmasi' },
    { id: 'memuat', label: 'Loading & skeleton' },
] as const

const TYPE_SCALE = [
    { utility: 'text-heading-4', figma: 'Heading/4', spec: 'Roboto 24 / 700' },
    { utility: 'text-heading-6', figma: 'Heading/6', spec: 'Roboto 18 / 700' },
    { utility: 'text-subtitle-s', figma: 'Subtitle/S', spec: 'Roboto 14 / 500' },
    { utility: 'text-body-m', figma: 'Body/M', spec: 'Roboto 16 / 400' },
    { utility: 'text-body-s', figma: 'Body/S', spec: 'Roboto 14 / 400' },
    { utility: 'text-body-xs', figma: 'Body/XS', spec: 'Roboto 12 / 400' },
    { utility: 'text-button-m', figma: 'Button/M', spec: 'Roboto 16 / 500' },
    { utility: 'text-button-s', figma: 'Button/S', spec: 'Roboto 14 / 500' },
    { utility: 'text-nav-l', figma: 'Inter/Body/Large/16', spec: 'Inter 16 / 400' },
    { utility: 'text-nav-l-semibold', figma: 'Inter/Body/Large/16/Semibold', spec: 'Inter 16 / 600' },
    { utility: 'text-nav-m', figma: 'Inter/Body/Medium/14', spec: 'Inter 14 / 400' },
    { utility: 'text-nav-m-semibold', figma: 'Inter/Body/Medium/14/Semibold', spec: 'Inter 14 / 600' },
]

/** Kelas ditulis utuh, bukan dirangkai — Tailwind memindai teks sumber. */
const SWATCHES = [
    { klass: 'bg-cool-10', name: 'cool-10', hex: '#F2F4F8' },
    { klass: 'bg-cool-20', name: 'cool-20', hex: '#DDE1E6' },
    { klass: 'bg-cool-30', name: 'cool-30', hex: '#C1C7CD' },
    { klass: 'bg-cool-40', name: 'cool-40', hex: '#A2A9B0' },
    { klass: 'bg-cool-60', name: 'cool-60', hex: '#697077' },
    { klass: 'bg-cool-70', name: 'cool-70', hex: '#4D5358' },
    { klass: 'bg-cool-80', name: 'cool-80', hex: '#343A3F' },
    { klass: 'bg-cool-90', name: 'cool-90', hex: '#21272A' },
    { klass: 'bg-cool-100', name: 'cool-100', hex: '#121619' },
    { klass: 'bg-primary-60', name: 'primary-60', hex: '#E1B762' },
    { klass: 'bg-primary-90', name: 'primary-90', hex: '#001D6C' },
    { klass: 'bg-danger', name: 'danger', hex: '#DA1E28' },
    { klass: 'bg-shell', name: 'shell', hex: '#101010' },
    { klass: 'bg-canvas', name: 'canvas', hex: '#E8E8E8' },
    { klass: 'bg-surface', name: 'surface', hex: '#FFFFFF' },
]

const ICONS = [
    { component: PhPlus, name: 'PhPlus', replaces: 'jam-icons / plus' },
    { component: PhTrash, name: 'PhTrash', replaces: 'feather / trash-2' },
    { component: PhPencilSimple, name: 'PhPencilSimple', replaces: 'iconoir / edit' },
    { component: PhMagnifyingGlass, name: 'PhMagnifyingGlass', replaces: 'jam-icons / search' },
    { component: PhDotsThreeOutline, name: 'PhDotsThreeOutline', replaces: 'jam-icons / more-horizontal-f' },
]

// --- state contoh hidup ---------------------------------------------------

const text = ref('')
const longText = ref('')
const errorText = ref('')

const toggleOn = ref(true)
const toggleOff = ref(false)
const checkHome = ref(true)
const checkDomino = ref(false)
const radioType = ref('event')

const selectValue = ref<string | number | null>(null)
const groupedValue = ref<string | number | null>(null)

const editorFull = ref('<p>Ketik di sini. Toolbar-nya lengkap.</p>')
const editorBasic = ref('<p>Hanya <strong>tebal</strong>, <em>miring</em>, dan daftar.</p>')

const reorderRows = [
    { id: 1, label: 'Apa itu domino?' },
    { id: 2, label: 'Bagaimana cara mendaftar?' },
    { id: 3, label: 'Di mana rulebook-nya?' },
]

const reorderRich = [
    { id: 1, label: 'Apa itu domino?', note: 'General' },
    { id: 2, label: 'Bagaimana cara mendaftar?', note: 'Tournament' },
]

const page = ref(2)

const confirmInfo = ref(false)
const confirmDelete = ref(false)

const columns: TableColumn[] = [
    { key: 'name', label: 'Category Name' },
    { key: 'usage', label: 'Number of Usage' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const rows = ref([
    { id: 1, name: 'Tournament', usage: 13, active: true },
    { id: 2, name: 'DWF', usage: 8, active: false },
    { id: 3, name: 'Federation', usage: 4, active: true },
])
</script>

<template>
    <Head title="Design System" />

    <AdminLayout>
        <PageHeader title="Design System">
            <template #description>
                Seluruh komponen bersama, contoh hidupnya, dan kode yang bisa disalin. Halaman
                ini alat kerja — bukan bagian dari wireframe.
            </template>
        </PageHeader>

        <nav class="flex flex-wrap gap-2" aria-label="Daftar bagian">
            <a
                v-for="section in SECTIONS"
                :key="section.id"
                :href="`#${section.id}`"
                class="border border-cool-20 bg-surface px-3 py-1.5 text-body-xs text-cool-70 transition-colors hover:border-cool-40 hover:text-cool-90"
            >
                {{ section.label }}
            </a>
        </nav>

        <!-- ================================================== Mulai -->
        <CardSection id="mulai" title="Mulai dari sini">
            <p class="text-body-s text-cool-60">
                Kerangka satu halaman backoffice. Semua halaman memakai bentuk ini:
                <code class="text-editor-80">AdminLayout</code> membungkus,
                <code class="text-editor-80">PageHeader</code> di atas, isinya di dalam
                <code class="text-editor-80">CardSection</code>.
            </p>
            <CodeBlock :code="snippet.PAGE_SKELETON" />

            <p class="text-body-s text-cool-60">
                Route dan menunya. Selama <code class="text-editor-80">built</code> masih
                <code class="text-editor-80">false</code>, tujuan itu dilayani
                <code class="text-editor-80">PlaceholderController</code>; begitu modulnya jadi,
                ubah jadi <code class="text-editor-80">true</code> supaya route placeholder-nya
                berhenti didaftarkan.
            </p>
            <CodeBlock :code="snippet.ROUTE_SKELETON" />
        </CardSection>

        <!-- ================================================== Tipografi -->
        <CardSection id="tipografi" title="Tipografi">
            <p class="text-body-s text-cool-60">
                Tiga family: Inter untuk sidebar, Roboto untuk konten, Plus Jakarta Sans untuk
                chrome editor. Nama utility sama persis dengan nama gaya di Figma supaya bisa
                dicocokkan satu-satu saat review desain.
            </p>

            <div class="flex flex-col border border-cool-20 bg-surface">
                <div
                    v-for="style in TYPE_SCALE"
                    :key="style.utility"
                    class="flex flex-wrap items-baseline justify-between gap-4 border-t border-cool-20 px-4 py-3 first:border-t-0"
                >
                    <span :class="style.utility" class="text-cool-90">
                        Domino World Federation
                    </span>
                    <span class="flex shrink-0 flex-col items-end">
                        <code class="text-body-xs text-editor-80">{{ style.utility }}</code>
                        <span class="text-body-xs text-cool-60">
                            {{ style.figma }} · {{ style.spec }}
                        </span>
                    </span>
                </div>
            </div>

            <CodeBlock :code="snippet.TYPOGRAPHY" />
        </CardSection>

        <!-- ================================================== Warna -->
        <CardSection id="warna" title="Warna">
            <p class="text-body-s text-cool-60">
                Dibaca dari wireframe. Nilai hex tidak pernah ditulis langsung di komponen —
                selalu lewat token ini. Palet gelapnya menukar arti CoolGray 10↔90, jadi
                komponen yang memakai token akan ikut benar tanpa diubah.
            </p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div
                    v-for="swatch in SWATCHES"
                    :key="swatch.name"
                    class="flex flex-col gap-1.5"
                >
                    <div
                        class="h-14 w-full border border-cool-20"
                        :class="swatch.klass"
                    />
                    <code class="text-body-xs text-editor-80">{{ swatch.name }}</code>
                    <span class="text-body-xs text-cool-60">{{ swatch.hex }}</span>
                </div>
            </div>

            <CodeBlock :code="snippet.COLOR" />
        </CardSection>

        <!-- ================================================== Ikon -->
        <CardSection id="ikon" title="Ikon">
            <p class="text-body-s text-cool-60">
                Satu library: Phosphor. Wireframe mencampur empat set berbeda; padanan
                lengkapnya di <code class="text-editor-80">docs/DESIGN-TOKENS.md</code> §6.
            </p>

            <div class="flex flex-wrap gap-4">
                <div
                    v-for="icon in ICONS"
                    :key="icon.name"
                    class="flex min-w-[180px] flex-col items-start gap-2 border border-cool-20 bg-surface p-4"
                >
                    <component :is="icon.component" :size="24" class="text-cool-90" />
                    <code class="text-body-xs text-editor-80">{{ icon.name }}</code>
                    <span class="text-body-xs text-cool-60">menggantikan {{ icon.replaces }}</span>
                </div>
            </div>

            <CodeBlock :code="snippet.ICON" />
        </CardSection>

        <!-- ================================================== Tombol -->
        <CardSection id="tombol" title="Tombol">
            <DemoBlock
                title="Varian"
                description="Filled untuk aksi utama, Outline untuk Cancel/Export, Link untuk aksi tersier."
                :code="snippet.BUTTON_VARIANTS"
            >
                <AppButton>Posting</AppButton>
                <AppButton variant="outline">Cancel</AppButton>
                <AppButton variant="link">Lihat semua</AppButton>
                <AppButton disabled>Nonaktif</AppButton>
            </DemoBlock>

            <DemoBlock title="Ukuran" :code="snippet.BUTTON_SIZES">
                <AppButton size="m">Save</AppButton>
                <AppButton size="s" variant="outline">Add Other</AppButton>
            </DemoBlock>

            <DemoBlock title="Dengan ikon" :code="snippet.BUTTON_ICON">
                <AppButton>
                    <template #iconLeft><PhPlus :size="24" /></template>
                    Add Category
                </AppButton>
                <AppButton variant="outline" size="s">
                    <template #iconLeft><PhTrash :size="20" /></template>
                    Hapus
                </AppButton>
            </DemoBlock>

            <DemoBlock
                title="Sebagai tautan"
                description="Beri href dan komponennya berubah jadi <Link> Inertia — bukan <button> yang memanggil router. Bedanya terasa saat orang klik-tengah."
                :code="snippet.BUTTON_LINK"
            >
                <AppButton href="/dashboard" variant="outline">Ke Dashboard</AppButton>
                <AppButton href="/assets/images/navbar-logo.svg" external variant="link">
                    Buka aset
                </AppButton>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Form -->
        <CardSection id="form" title="Field & baris form">
            <DemoBlock title="Field dasar" :code="snippet.FIELD_BASIC">
                <div class="w-full max-w-md">
                    <AppField v-model="text" placeholder="Judul berita" />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Keadaan"
                description="Textarea, nonaktif, dan galat. Galat memasang aria-invalid dan aria-describedby, lalu diumumkan lewat role=alert."
                :code="snippet.FIELD_STATES"
            >
                <div class="flex w-full max-w-md flex-col gap-4">
                    <AppField v-model="longText" textarea placeholder="Deskripsi panjang" />
                    <AppField v-model="text" disabled placeholder="Nonaktif" />
                    <AppField v-model="errorText" error="Judul wajib diisi." />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Baris form"
                description="Pola yang berulang di seluruh wireframe. FormRow yang membuat id-nya dan meneruskannya lewat slot — itu yang membuat klik label memfokuskan field."
                :code="snippet.FORM_ROW"
            >
                <FormRow
                    label="Category Name"
                    description="Enter category name (e.g., Tournament, DWF)."
                    required
                >
                    <template #default="{ id }">
                        <AppField :id="id" v-model="text" placeholder="Placeholder" />
                    </template>
                </FormRow>
            </DemoBlock>

            <DemoBlock title="Baris form ringkas" :code="snippet.FORM_ROW_COMPACT">
                <FormRow
                    label="Status"
                    description="Active categories will appear in the news."
                    compact
                >
                    <AppToggle v-model="toggleOn" label="Active" />
                </FormRow>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Kontrol -->
        <CardSection id="kontrol" title="Toggle, checkbox, radio">
            <DemoBlock title="Toggle" :code="snippet.TOGGLE">
                <AppToggle v-model="toggleOn" label="Active" />
                <AppToggle v-model="toggleOff" label="Nonaktif" />
                <AppToggle :model-value="false" label="Terkunci" disabled />
            </DemoBlock>

            <DemoBlock
                title="Checkbox"
                description="hint mencetak teks abu di kanan label — dipakai penghitung “(1/3)” di layar Add FAQ."
                :code="snippet.CHECKBOX"
            >
                <AppCheckbox v-model="checkHome" label="Home Page" hint="(1/3)" />
                <AppCheckbox v-model="checkDomino" label="Domino Page" hint="(0/3)" />
            </DemoBlock>

            <DemoBlock
                title="Radio"
                description="name harus sama untuk satu kelompok, kalau tidak panah keyboard tidak berpindah antar pilihan."
                :code="snippet.RADIO"
            >
                <AppRadio v-model="radioType" value="event" name="ds-type" label="Event" />
                <AppRadio
                    v-model="radioType"
                    value="tournament"
                    name="ds-type"
                    label="Tournament"
                />
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Pilihan -->
        <CardSection id="pilihan" title="Pilihan (select)">
            <p class="text-body-s text-cool-60">
                Membungkus <code class="text-editor-80">&lt;select&gt;</code> asli, bukan
                menggantinya dengan daftar buatan sendiri: yang asli sudah membawa navigasi
                keyboard, pencarian ketik, dan tampilan asli sistem di ponsel.
            </p>

            <DemoBlock title="Pilihan datar" :code="snippet.SELECT_FIELD">
                <div class="w-full max-w-md">
                    <SelectField
                        v-model="selectValue"
                        :options="[
                            { value: 1, label: 'Tournament' },
                            { value: 2, label: 'Membership' },
                        ]"
                        placeholder="Pilih kategori"
                    />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Dikelompokkan"
                description="groups menggantikan options, bukan menemaninya. Dipakai saat nama kelompoknya ikut menjawab pertanyaan yang sedang dijawab."
                :code="snippet.SELECT_GROUPED"
            >
                <div class="w-full max-w-md">
                    <SelectField
                        v-model="groupedValue"
                        :groups="[
                            { label: 'General', options: [{ value: 1, label: 'Apa itu domino?' }] },
                            {
                                label: 'Tournament',
                                options: [{ value: 2, label: 'Bagaimana cara mendaftar?' }],
                            },
                        ]"
                        placeholder="Tambah pertanyaan"
                    />
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Editor -->
        <CardSection id="editor" title="Editor teks kaya">
            <ContextNote tone="security">
                HTML yang keluar dari editor WAJIB lewat
                <code class="text-editor-80">Purifier::clean()</code> di server sebelum
                disimpan. Editor adalah kenyamanan mengetik, bukan batas keamanan — isinya
                nanti tampil di situs publik.
            </ContextNote>

            <DemoBlock
                title="Penuh"
                description="Isi berita dan jawaban FAQ. Gambar diunggah ke /editor/images, bukan disisipkan sebagai base64."
                :code="snippet.RICH_TEXT_EDITOR"
            >
                <div class="w-full">
                    <RichTextEditor v-model="editorFull" />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Dasar"
                description="Tanpa pemilih judul, sorot, atau gambar. Toolbar-nya HARUS cocok dengan profil Purifier yang dipakai server — tombol yang ada di satu sisi tapi tidak di sisi lain menghapus pekerjaan penulisnya saat disimpan."
                :code="snippet.RICH_TEXT_EDITOR_BASIC"
            >
                <div class="w-full">
                    <RichTextEditor v-model="editorBasic" variant="basic" />
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Urutan -->
        <CardSection id="urutan" title="Daftar bisa diurutkan">
            <p class="text-body-s text-cool-60">
                Menyediakan DUA cara memindahkan baris: seret-lepas dan tombol naik/turun.
                Tombolnya bukan pelengkap — seret-lepas mustahil dipakai dengan keyboard dan
                berat dipakai dengan pembaca layar, jadi tanpa tombol itu fitur ini hanya ada
                untuk sebagian orang.
            </p>

            <DemoBlock title="Dasar" :code="snippet.REORDER_LIST">
                <div class="w-full max-w-xl">
                    <ReorderList :items="reorderRows" @change="() => {}" />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Dengan keterangan dan tindakan baris"
                description="note mencetak keterangan kecil di sebelah label — menyeret baris di daftar panjang tanpa tahu baris itu milik apa adalah menyeret buta. Slot rowActions untuk tindakan per baris."
                :code="snippet.REORDER_LIST_RICH"
            >
                <div class="w-full max-w-xl">
                    <ReorderList :items="reorderRich" @change="() => {}">
                        <template #rowActions="{ row }">
                            <IconButton :label="`Keluarkan ${row.label}`" tone="danger">
                                <PhX :size="16" aria-hidden="true" />
                            </IconButton>
                        </template>
                    </ReorderList>
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Tabel -->
        <CardSection id="tabel" title="Tabel">
            <DemoBlock
                title="Kolom, baris, dan slot per sel"
                description="Slot bernama cell.<key> menggantikan isi sel. Tanpa slot, nilainya dicetak apa adanya."
                :code="snippet.TABLE"
            >
                <div class="w-full">
                    <DataTable :columns="columns" :rows="rows" row-key="id">
                        <template #cell.status="{ row }">
                            <AppToggle
                                :model-value="(row as { active: boolean }).active"
                                :label="`Status ${(row as { name: string }).name}`"
                                @update:model-value="(row as { active: boolean }).active = $event"
                            />
                        </template>

                        <template #cell.actions="{ row }">
                            <button
                                type="button"
                                class="cursor-pointer text-cool-90"
                                :aria-label="`Aksi untuk ${(row as { name: string }).name}`"
                            >
                                <PhDotsThreeOutline :size="20" weight="fill" />
                            </button>
                        </template>
                    </DataTable>
                </div>
            </DemoBlock>

            <DemoBlock
                title="Keadaan kosong"
                description="Selalu isi empty-message dengan kalimat yang menyebut apa yang kosong dan apa langkah berikutnya."
                :code="snippet.TABLE_EMPTY"
            >
                <div class="w-full">
                    <DataTable
                        :columns="columns"
                        :rows="[]"
                        row-key="id"
                        empty-message="Belum ada kategori. Tambahkan yang pertama."
                    />
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Pagination -->
        <CardSection id="pagination" title="Pagination">
            <DemoBlock
                title="Deretan halaman"
                description="Jumlah tombolnya dihitung, bukan disalin dari desain: wireframe menggambar satu keadaan (11 halaman), dan bentuk yang di-hardcode akan menunjuk ke halaman yang tidak ada saat datanya cuma tiga halaman."
                :code="snippet.PAGINATION"
            >
                <div class="w-full">
                    <AppPagination
                        :current-page="page"
                        :last-page="11"
                        :href-for="(n: number) => `?page=${n}`"
                        @navigate="page = $event"
                    />
                    <p class="mt-3 text-center text-body-xs text-cool-60">
                        Halaman aktif: {{ page }}
                    </p>
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Navigasi -->
        <CardSection id="navigasi" title="Breadcrumb & kepala halaman">
            <DemoBlock
                title="Breadcrumbs"
                description="Ruas terakhir tidak pernah jadi tautan, walau diberi href."
                :code="snippet.BREADCRUMBS"
            >
                <Breadcrumbs
                    :items="[
                        { label: 'News', href: '/news' },
                        { label: 'News List', href: '/news' },
                        { label: 'Add News' },
                    ]"
                />
            </DemoBlock>

            <DemoBlock
                title="Kepala halaman"
                description="PageHeader sudah memuat breadcrumb-nya; jangan memasang dua-duanya terpisah."
                :code="snippet.PAGE_HEADER"
            >
                <div class="w-full">
                    <PageHeader
                        title="News Category"
                        :breadcrumbs="[{ label: 'News' }, { label: 'News Category' }]"
                    >
                        <template #description>
                            Kelola kategori yang dipakai artikel berita.
                        </template>
                        <template #actions>
                            <AppButton variant="outline">Export</AppButton>
                            <AppButton>
                                <template #iconLeft><PhPlus :size="24" /></template>
                                Add Category
                            </AppButton>
                        </template>
                    </PageHeader>
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Kartu -->
        <CardSection id="kartu" title="Kartu">
            <DemoBlock
                title="CardSection"
                description="Kartu putih padding 24 dengan Heading/6 — pembungkus tiap blok form di wireframe. Slot #header mengisi sisi kanan judul."
                :code="snippet.CARD_SECTION"
            >
                <div class="w-full bg-canvas p-4">
                    <CardSection title="Category Data">
                        <template #header>
                            <AppButton variant="link" size="s">Reset</AppButton>
                        </template>
                        <FormRow label="Category Name" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="text" placeholder="Placeholder" />
                            </template>
                        </FormRow>
                    </CardSection>
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Catatan konteks -->
        <CardSection id="catatan" title="Catatan konteks">
            <DemoBlock
                title="ContextNote"
                description="Pita keterangan di atas daftar, atau panel di dalam formulir kalau diberi judul. Dipakai Admin Users, Audit Log, Roles, dan IP Whitelist — isinya selalu kalimat yang mengubah cara orang membaca layarnya, bukan sambutan."
                :code="snippet.CONTEXT_NOTE"
            >
                <div class="flex w-full flex-col gap-3">
                    <ContextNote>
                        System roles can be inspected but not deleted. Custom roles with assigned
                        admins must be reassigned before deletion.
                    </ContextNote>

                    <ContextNote tone="warning">
                        Only active rules are enforced for Backoffice access. IP Whitelist changes
                        should be restricted to Super Admin.
                    </ContextNote>

                    <ContextNote tone="security" title="Validation &amp; Security">
                        Supports IPv4, IPv6, and CIDR notation.
                    </ContextNote>
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Progress step -->
        <CardSection id="progres" title="Progress step">
            <DemoBlock
                title="ProgressSteps"
                description="Sidebar untuk formulir panjang (Add Tournament). Tiap langkah menggambar cincin berisi pecahan section yang sudah terisi, menaut ke `#section-{id}`, dan langkah terakhir sengaja tanpa garis penghubung."
                :code="snippet.PROGRESS_STEPS"
            >
                <div class="w-full bg-canvas p-4">
                    <ProgressSteps
                        label="Progres formulir"
                        :steps="[
                            { id: 'basic', label: 'Basic Information', progress: 1 },
                            { id: 'venue', label: 'Venue', progress: 0.5 },
                            { id: 'prize', label: 'Prize Information', progress: 0.25 },
                            { id: 'regulations', label: 'Regulations & Rules', progress: 0 },
                        ]"
                    />
                </div>
            </DemoBlock>
        </CardSection>

        <!-- ================================================== Dialog -->
        <CardSection id="dialog" title="Dialog konfirmasi">
            <DemoBlock
                title="Dua varian"
                description="Dibangun di atas <dialog> bawaan browser, jadi jebakan fokus, Escape, dan inert pada latar sudah benar tanpa ditulis."
                :code="snippet.CONFIRM_DIALOG"
            >
                <AppButton variant="outline" @click="confirmInfo = true">
                    Buka varian info
                </AppButton>
                <AppButton variant="outline" @click="confirmDelete = true">
                    Buka varian hapus
                </AppButton>
            </DemoBlock>

            <ConfirmDialog
                :open="confirmInfo"
                variant="info"
                title="Simpan sebagai draf?"
                description="Artikel belum akan tampil di situs publik sampai kamu menerbitkannya."
                confirm-label="Simpan draf"
                @confirm="confirmInfo = false"
                @cancel="confirmInfo = false"
            />

            <ConfirmDialog
                :open="confirmDelete"
                variant="deletion"
                title="Hapus kategori ini?"
                description="Artikel yang memakainya akan kehilangan kategori. Tindakan ini tidak bisa dibatalkan."
                confirm-label="Hapus"
                @confirm="confirmDelete = false"
                @cancel="confirmDelete = false"
            />
        </CardSection>

        <!-- ================================================== Memuat -->
        <CardSection id="memuat" title="Loading & skeleton">
            <div class="border-l-4 border-primary-60 bg-cool-10 px-4 py-3">
                <p class="text-body-s text-cool-90">
                    <strong>Bar loading sudah global</strong> — tidak perlu dipasang per halaman.
                    Skeleton <em>tidak</em> dipasang global: pindah halaman di Inertia tidak
                    pernah melewati keadaan “halaman kosong menunggu data”, jadi tidak ada
                    tempat untuk menaruhnya. Skeleton dipakai di dua tempat saja — prop yang
                    ditunda (<code class="text-editor-80">Inertia::defer</code>) dan muat ulang
                    sebagian (<code class="text-editor-80">router.reload({ only })</code>).
                </p>
            </div>

            <DemoBlock
                title="Bar loading"
                description="Terpasang sekali di app.ts: garis emas 3px di tepi atas layar. Warnanya sama dengan toggle aktif dan pagination aktif — supaya terbaca sebagai bagian sistem yang sama, bukan warna bawaan Inertia."
                :code="snippet.PROGRESS_BAR"
            >
                <p class="text-body-s text-cool-60">
                    Klik menu mana pun di sidebar untuk melihatnya. Dengan
                    <code class="text-editor-80">delay</code> bawaan Inertia (250&nbsp;ms) bar
                    ini tidak pernah muncul sekali pun di backoffice — navigasinya selesai
                    dalam belasan milidetik, jauh sebelum tundaannya habis.
                </p>
            </DemoBlock>

            <DemoBlock
                title="Skeleton dasar"
                description="Satu balok berdenyut. Ukurannya diatur pemanggil lewat kelas. Denyutnya mati sendiri kalau sistem menyalakan “Reduce Motion”."
                :code="snippet.SKELETON_BASIC"
            >
                <div class="flex flex-col gap-3">
                    <Skeleton class="h-3.5 w-40" />
                    <Skeleton class="h-6 w-64" />
                    <Skeleton class="size-10" circle />
                </div>
            </DemoBlock>

            <DemoBlock
                title="Skeleton tabel"
                description="Rangka luarnya sama persis dengan DataTable — border, header, padding sel — supaya tabelnya tidak melompat saat datanya datang."
                :code="snippet.SKELETON_TABLE"
            >
                <div class="w-full">
                    <SkeletonTable :columns="4" :rows="4" label="Contoh: memuat kategori…" />
                </div>
            </DemoBlock>

            <div class="flex flex-col gap-3">
                <h3 class="text-subtitle-s text-cool-90">Pola 1 — prop yang ditunda</h3>
                <p class="text-body-xs text-cool-60">
                    Halaman tampil segera, datanya menyusul di round-trip kedua. Ini satu-satunya
                    saat skeleton benar-benar terlihat saat pindah halaman. Pakai untuk daftar
                    yang query-nya berat.
                </p>
                <CodeBlock :code="snippet.SKELETON_DEFERRED" />
                <CodeBlock :code="snippet.SKELETON_DEFERRED_VUE" />
            </div>

            <div class="flex flex-col gap-3">
                <h3 class="text-subtitle-s text-cool-90">Pola 2 — muat ulang sebagian</h3>
                <p class="text-body-xs text-cool-60">
                    Ganti filter atau halaman tanpa membangun ulang seluruh layar. Kerangkanya
                    tetap, jadi skeleton cukup menutupi area tabelnya saja.
                </p>
                <CodeBlock :code="snippet.SKELETON_PARTIAL" />
            </div>
        </CardSection>
    </AdminLayout>
</template>
