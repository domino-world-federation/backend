/**
 * Cuplikan kode untuk halaman Design System.
 *
 * Dipisah dari komponennya karena template Vue di dalam template Vue harus
 * di-escape habis-habisan supaya tidak ikut dikompilasi. Sebagai string biasa
 * di file `.ts`, kodenya persis seperti yang harus ditulis orang.
 *
 * Satu larangan: jangan pernah menulis penutup tag script secara harfiah di
 * dalam string mana pun di file ini kalau file ini nanti dipindah ke dalam
 * blok `<script setup>` sebuah `.vue` — parser SFC memotong file di kemunculan
 * pertamanya. Di sini aman karena file-nya `.ts` murni.
 */

export const BUTTON_VARIANTS = `<AppButton>Posting</AppButton>
<AppButton variant="outline">Cancel</AppButton>
<AppButton variant="link">Lihat semua</AppButton>
<AppButton disabled>Nonaktif</AppButton>`

export const BUTTON_SIZES = `<!-- size="m" (bawaan, tinggi 48) dan size="s" (tinggi 40) -->
<AppButton size="m">Save</AppButton>
<AppButton size="s" variant="outline">Add Other</AppButton>`

export const BUTTON_ICON = `<AppButton>
    <template #iconLeft><PhPlus :size="24" /></template>
    Add Category
</AppButton>

<AppButton variant="outline" size="s">
    <template #iconLeft><PhTrash :size="20" /></template>
    Hapus
</AppButton>`

export const BUTTON_LINK = `<!-- Ada href: merender <Link> Inertia, bukan <button>.
     Klik-tengah dan "buka di tab baru" ikut bekerja. -->
<AppButton href="/news" variant="outline">Ke News</AppButton>

<!-- external: <a> biasa, untuk unduhan atau tautan ke luar -->
<AppButton href="/storage/berkas.pdf" external variant="link">Unduh PDF</AppButton>`

export const FIELD_BASIC = `<script setup lang="ts">
import { ref } from 'vue'
import AppField from '@/Components/AppField.vue'

const title = ref('')
<\/script>

<template>
    <AppField v-model="title" placeholder="Judul berita" />
</template>`

export const FIELD_STATES = `<AppField v-model="value" placeholder="Placeholder" />
<AppField v-model="value" textarea placeholder="Deskripsi panjang" />
<AppField v-model="value" disabled placeholder="Nonaktif" />
<AppField v-model="value" error="Judul wajib diisi." />`

export const FORM_ROW = `<!-- Pola baris form yang berulang di seluruh wireframe:
     label + deskripsi di kiri, kontrol di kanan.
     FormRow yang membuat id-nya, lalu meneruskannya lewat slot —
     itu yang membuat <label for> benar-benar menunjuk ke field. -->
<FormRow
    label="Category Name"
    description="Enter category name (e.g., Tournament, DWF)."
    required
>
    <template #default="{ id }">
        <AppField :id="id" v-model="form.name" placeholder="Placeholder" />
    </template>
</FormRow>`

export const FORM_ROW_COMPACT = `<!-- compact: untuk kontrol yang lebarnya mengikuti isi -->
<FormRow
    label="Status"
    description="Active categories will appear in the news."
    compact
>
    <AppToggle v-model="form.active" label="Active" />
</FormRow>`

export const TOGGLE = `<AppToggle v-model="active" label="Active" />

<!-- Merender <button role="switch">, jadi spasi/enter dan
     pengumuman on/off datang dari platform. -->`

export const CHECKBOX = `<AppCheckbox v-model="onHome" label="Home Page" hint="(1/3)" />
<AppCheckbox v-model="onDomino" label="Domino Page" hint="(0/3)" />`

export const RADIO = `<!-- name harus sama untuk satu kelompok -->
<AppRadio v-model="type" value="event" name="gallery-type" label="Event" />
<AppRadio v-model="type" value="tournament" name="gallery-type" label="Tournament" />`

export const TABLE = `<script setup lang="ts">
import DataTable from '@/Components/DataTable.vue'
import type { TableColumn } from '@/types'

const columns: TableColumn[] = [
    { key: 'name', label: 'Category Name' },
    { key: 'usage', label: 'Number of Usage' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', width: '40px', align: 'right' },
]

const rows = [
    { id: 1, name: 'Tournament', usage: 13, active: true },
    { id: 2, name: 'DWF', usage: 8, active: false },
]
<\/script>

<template>
    <DataTable :columns="columns" :rows="rows" row-key="id">
        <!-- Slot per kolom: cell.<key>. Tanpa slot, nilainya dicetak apa adanya. -->
        <template #cell.status="{ row }">
            <AppToggle :model-value="row.active" @update:model-value="toggle(row)" />
        </template>

        <template #cell.actions>
            <button type="button" aria-label="Aksi baris">
                <PhDotsThreeOutline :size="20" weight="fill" />
            </button>
        </template>
    </DataTable>
</template>`

export const TABLE_EMPTY = `<!-- Baris kosong punya pesannya sendiri -->
<DataTable
    :columns="columns"
    :rows="[]"
    row-key="id"
    empty-message="Belum ada kategori. Tambahkan yang pertama."
/>`

export const PAGINATION = `<AppPagination
    :current-page="page"
    :last-page="11"
    :href-for="(n) => \`/news?page=\${n}\`"
    @navigate="page = $event"
/>

<!-- href-for wajib: tombolnya tetap <a> dengan URL sungguhan, jadi
     klik-tengah bekerja meski @navigate yang menangani klik biasa. -->`

export const BREADCRUMBS = `<Breadcrumbs
    :items="[
        { label: 'News', href: '/news' },
        { label: 'News List', href: '/news' },
        { label: 'Add News' },
    ]"
/>

<!-- Ruas terakhir tidak pernah jadi tautan, walau diberi href. -->`

export const PAGE_HEADER = `<PageHeader title="News Category" :breadcrumbs="crumbs">
    <template #description>Kelola kategori yang dipakai artikel berita.</template>

    <template #actions>
        <AppButton variant="outline">Export</AppButton>
        <AppButton>
            <template #iconLeft><PhPlus :size="24" /></template>
            Add Category
        </AppButton>
    </template>
</PageHeader>`

export const CARD_SECTION = `<CardSection title="Category Data">
    <FormRow label="Category Name" required>
        <template #default="{ id }">
            <AppField :id="id" v-model="form.name" />
        </template>
    </FormRow>
</CardSection>`

export const CONFIRM_DIALOG = `<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'

const confirming = ref(false)
const processing = ref(false)

function destroy() {
    processing.value = true
    router.delete('/news/1', {
        onFinish: () => {
            processing.value = false
            confirming.value = false
        },
    })
}
<\/script>

<template>
    <AppButton variant="outline" @click="confirming = true">Hapus</AppButton>

    <ConfirmDialog
        :open="confirming"
        variant="deletion"
        title="Hapus kategori ini?"
        description="Artikel yang memakainya akan kehilangan kategori. Tindakan ini tidak bisa dibatalkan."
        confirm-label="Hapus"
        :processing="processing"
        @confirm="destroy"
        @cancel="confirming = false"
    />
</template>`

export const TYPOGRAPHY = `<!-- Jangan menulis font-size/weight langsung. Selalu lewat utility ini —
     namanya sama persis dengan nama gaya di Figma. -->
<h1 class="text-heading-4">Judul halaman</h1>
<h2 class="text-heading-6">Judul kartu</h2>
<p class="text-body-m">Isi field</p>
<p class="text-body-s">Teks isi</p>
<p class="text-body-xs">Keterangan kecil</p>`

export const COLOR = `<!-- Warna juga lewat token, tidak pernah hex langsung -->
<p class="text-cool-90">Teks utama</p>
<p class="text-cool-60">Teks sekunder</p>
<div class="bg-surface border border-cool-20">Kartu</div>
<span class="text-danger">Galat</span>`

export const ICON = `<script setup lang="ts">
// Satu library saja: Phosphor. Wireframe mencampur empat set;
// padanannya ada di docs/DESIGN-TOKENS.md §6.
import { PhPlus, PhTrash, PhMagnifyingGlass } from '@phosphor-icons/vue'
<\/script>

<template>
    <PhPlus :size="24" />
    <PhTrash :size="20" />
    <PhMagnifyingGlass :size="24" />
</template>`

export const PROGRESS_BAR = `// resources/js/app.ts — sudah terpasang, tidak perlu ditulis lagi.
createInertiaApp({
    // …
    progress: {
        color: '#E1B762',
        delay: 0,            // WAJIB nol di sini — lihat catatan di bawah
        showSpinner: false,  // spinner bawaan menimpa ikon topbar
    },
})

// Kenapa delay: 0.
// Inertia memasang setTimeout(start, delay) saat kunjungan mulai, lalu
// membatalkannya di 'inertia:finish' — dan kalau bar-nya belum sempat mulai,
// ia langsung keluar tanpa merender apa pun:
//
//     function finish(event, timeout) {
//         clearTimeout(timeout)
//         if (!progress.isStarted()) return
//         …
//
// Bawaannya 250 ms, sementara navigasi backoffice ini selesai dalam 14-21 ms.
// Artinya dengan nilai bawaan bar-nya TIDAK PERNAH muncul sekali pun.
//
// Tingginya dinaikkan jadi 3px lewat resources/css/app.css. Selektornya harus
// diawali 'html' — CSS Inertia disuntikkan saat runtime, jadi selalu datang
// setelah stylesheet kita dan menang kalau spesifisitasnya sama.`

export const SKELETON_BASIC = `<!-- Ukuran diatur lewat kelas; komponennya cuma balok berdenyut -->
<Skeleton class="h-3.5 w-40" />
<Skeleton class="h-6 w-64" />
<Skeleton class="size-10" circle />`

export const SKELETON_TABLE = `<SkeletonTable :columns="4" :rows="5" label="Memuat kategori…" />`

export const SKELETON_DEFERRED = `// app/Http/Controllers/NewsController.php
// Prop yang ditunda dikirim di round-trip kedua. Halamannya tampil segera,
// tabelnya menyusul — dan itulah satu-satunya saat skeleton benar-benar terlihat.
return Inertia::render('News/Index', [
    'filters' => $request->only('status', 'category', 'q'),
    'rows' => Inertia::defer(fn () => NewsResource::collection(
        NewsArticle::query()->filter($request)->paginate()
    )),
]);`

export const SKELETON_DEFERRED_VUE = `<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3'
import DataTable from '@/Components/DataTable.vue'
import SkeletonTable from '@/Components/SkeletonTable.vue'

defineProps<{ rows?: Row[] }>()   // opsional — belum ada saat render pertama
<\/script>

<template>
    <Deferred :data="['rows']">
        <template #fallback>
            <SkeletonTable :columns="4" label="Memuat berita…" />
        </template>

        <DataTable :columns="columns" :rows="rows!" row-key="id" />
    </Deferred>
</template>`

export const SKELETON_PARTIAL = `<script setup lang="ts">
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

const status = ref('')
const loading = ref(false)

// Ganti filter -> muat ulang HANYA prop 'rows'. Sisa halaman tidak disentuh,
// jadi skeleton cukup dipasang di area tabelnya saja.
watch(status, (value) => {
    router.reload({
        only: ['rows'],
        data: { status: value },
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    })
})
<\/script>

<template>
    <SkeletonTable v-if="loading" :columns="4" />
    <DataTable v-else :columns="columns" :rows="rows" row-key="id" />
</template>`

export const PAGE_SKELETON = `<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
<\/script>

<template>
    <Head title="News Category" />

    <AdminLayout>
        <PageHeader title="News Category" :breadcrumbs="[{ label: 'News' }, { label: 'Category' }]" />

        <CardSection title="Category Data">
            <!-- isi halaman -->
        </CardSection>
    </AdminLayout>
</template>`

export const ROUTE_SKELETON = `// routes/web.php — di dalam grup middleware 'auth'
Route::get('/news/categories', [NewsCategoryController::class, 'index'])
    ->name('news.categories.index');

// app/Support/Navigation.php — ubah entri menunya jadi built: true
// supaya route placeholder-nya berhenti didaftarkan.
self::item('News Articles', 'CalendarBlank', 'news', built: true),`

export const CONTEXT_NOTE = `<!-- Pita keterangan di atas daftar. Isinya SELALU kalimat yang
     mengubah cara orang membaca layarnya — apa yang ditegakkan, apa
     yang tidak bisa dibatalkan, siapa yang boleh mengubah. -->
<ContextNote>{{ t('roles.context_note') }}</ContextNote>

<!-- Nada lain: emas untuk peringatan, perisai untuk keamanan. -->
<ContextNote tone="warning">{{ t('ip_whitelist.security_note') }}</ContextNote>

<!-- Dengan judul, ia jadi panel di dalam formulir, bukan pita. -->
<ContextNote tone="security" :title="t('ip_whitelist.validation_title')">
    {{ t('ip_whitelist.validation_body') }}
</ContextNote>`

export const PROGRESS_STEPS = `<!-- Sidebar untuk formulir panjang. Tiap langkah menggambar cincin
     berisi PECAHAN section yang sudah terisi — bukan cuma
     "selesai / belum". -->
<ProgressSteps
    :label="t('tournaments.progress')"
    :steps="[
        { id: 'basic', label: 'Basic Information', progress: 1 },
        { id: 'venue', label: 'Venue', progress: 0.5 },
        { id: 'prize', label: 'Prize Information', progress: 0 },
    ]"
/>

<!-- Yang dihitung field WAJIB saja: cincinnya menjawab "berapa lagi
     sebelum ini bisa disimpan", bukan "berapa kotak yang ada". -->`


// ---------------------------------------------------------------- Pilihan

export const SELECT_FIELD = `<SelectField
    v-model="categoryId"
    :options="[
        { value: 1, label: 'Tournament' },
        { value: 2, label: 'Membership' },
    ]"
    placeholder="Pilih kategori"
/>`

export const SELECT_GROUPED = `<!-- \`groups\` menggantikan \`options\`, bukan menemaninya.
     Dipakai saat nama kelompoknya ikut menjawab pertanyaan yang
     sedang dijawab — memilih FAQ dilakukan orang lewat kategorinya. -->
<SelectField
    v-model="faqId"
    :groups="[
        { label: 'General', options: [{ value: 1, label: 'Apa itu domino?' }] },
        { label: 'Tournament', options: [{ value: 2, label: 'Bagaimana cara mendaftar?' }] },
    ]"
    placeholder="Tambah pertanyaan"
/>`

// -------------------------------------------------------------- Urutan

export const REORDER_LIST = `<!-- Menyediakan DUA cara memindahkan baris: seret-lepas dan tombol
     naik/turun. Tombolnya bukan pelengkap — seret-lepas mustahil
     dipakai dengan keyboard.

     Urutannya disimpan di state lokal dan baru dikirim saat Simpan
     ditekan, supaya satu kali seret tidak jadi satu request. -->
<ReorderList :items="rows" @change="(ids) => (order = ids)" />

<AppButton :disabled="!dirty" @click="save">Simpan urutan</AppButton>`

export const REORDER_LIST_RICH = `<!-- \`note\` mencetak keterangan kecil di sebelah label. Ada karena
     menyeret baris di daftar panjang tanpa tahu baris itu MILIK apa
     adalah menyeret buta.

     Slot \`rowActions\` untuk tindakan per baris — apa yang boleh
     dilakukan pada satu baris urusan layar yang memakainya. -->
<ReorderList
    :items="[
        { id: 1, label: 'Apa itu domino?', note: 'General' },
        { id: 2, label: 'Bagaimana cara mendaftar?', note: 'Tournament' },
    ]"
    @change="reorder"
>
    <template #rowActions="{ row }">
        <IconButton :label="\`Keluarkan \${row.label}\`" tone="danger" @click="remove(row.id)">
            <PhX :size="16" aria-hidden="true" />
        </IconButton>
    </template>
</ReorderList>`

// -------------------------------------------------------------- Editor

export const RICH_TEXT_EDITOR = `<!-- Editor tiptap. HTML yang keluar dari sini TETAP dibersihkan
     \`Purifier::clean()\` di server — editor adalah kenyamanan
     mengetik, bukan batas keamanan.

     Gambar diunggah ke /editor/images, TIDAK disisipkan sebagai
     base64: gambar 1 MB sebagai data URI jadi ~1,4 MB teks di kolom
     body, ikut terkirim tiap muat halaman. -->
<RichTextEditor v-model="form.body" :error="form.errors.body" />`

export const RICH_TEXT_EDITOR_BASIC = `<!-- variant="basic" memangkas toolbar jadi penegasan di dalam kalimat
     dan daftar saja — tanpa pemilih judul, sorot, atau gambar.

     WAJIB dipasangkan dengan profil Purifier yang menyempit ke daftar
     yang sama (lihat profil \`legal\` di config/purifier.php). Tombol
     yang ada di satu sisi tapi tidak di sisi lain akan menghapus
     pekerjaan penulisnya saat disimpan, tanpa satu pun pesan. -->
<RichTextEditor
    v-model="block.description"
    variant="basic"
    :aria-label="\`Deskripsi konten \${index + 1}\`"
    :error="fieldError(index, 'description')"
/>`
