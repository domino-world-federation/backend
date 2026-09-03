<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Editor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Highlight from '@tiptap/extension-highlight'
import Image from '@tiptap/extension-image'
import {
    PhHighlighterCircle,
    PhImageSquare,
    PhSpinnerGap,
    PhListBullets,
    PhListNumbers,
    PhTextB,
    PhTextItalic,
    PhTextStrikethrough,
    PhTextUnderline,
} from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Editor isi berita dan jawaban FAQ (`341:4784`).
 *
 * Memakai tiptap, bukan `contenteditable` + `document.execCommand`: yang kedua
 * sudah dinyatakan usang, perilakunya berbeda di tiap browser, dan HTML yang
 * dihasilkannya berantakan — persis jenis keluaran yang nanti tampil di situs
 * publik.
 *
 * HTML yang keluar dari sini TETAP dibersihkan di server (`mews/purifier`).
 * Editor adalah kenyamanan mengetik, bukan batas keamanan.
 */
const props = withDefaults(
    defineProps<{
        id?: string
        error?: string
        /**
         * Nama kotaknya untuk pembaca layar, saat tidak ada `<label>` yang
         * menunjuknya. Blok berulang butuh ini: sepuluh kotak yang semuanya
         * bernama "Description" tidak memberi tahu yang mana.
         */
        ariaLabel?: string
        /**
         * `basic` memangkas toolbar jadi penegasan di dalam kalimat dan daftar
         * saja — tanpa pemilih judul, tanpa sorot, tanpa gambar.
         *
         * Dipakai blok halaman hukum: bloknya SUDAH punya judulnya sendiri di
         * field terpisah, jadi `h2` di dalam deskripsinya akan berdiri sejajar
         * dengan judul itu. Server memakai profil Purifier `legal` yang
         * menyempit ke daftar yang sama; kalau keduanya berbeda, yang menang
         * server — dan yang terlihat penulisnya adalah tombol yang menghapus
         * pekerjaannya sendiri saat disimpan.
         */
        variant?: 'full' | 'basic'
    }>(),
    { variant: 'full' },
)

const isBasic = props.variant === 'basic'

const { t } = useI18n()

const model = defineModel<string>({ default: '' })

const editor = new Editor({
    content: model.value,
    extensions: [
        StarterKit,
        Underline,
        Highlight,
        /*
         * `inline: false` — gambar berdiri sebagai bloknya sendiri.
         *
         * Gambar sebaris di dalam paragraf hampir selalu tidak disengaja:
         * ia mendorong tinggi barisnya dan membuat teks di sekitarnya melompat.
         * Yang dimaksud orang saat menyisipkan gambar di tengah tulisan adalah
         * "taruh di antara dua paragraf".
         */
        Image.configure({ inline: false, allowBase64: false }),
    ],
    editorProps: {
        attributes: {
            // Blok halaman hukum panjangnya satu-dua paragraf; kotak 220px
            // untuknya membuat halaman berisi sepuluh blok jadi lebih tinggi
            // dari yang bisa dibaca sekali gulir.
            class: `prose-dwf ${props.variant === 'basic' ? 'min-h-[140px]' : 'min-h-[220px]'} px-8 py-6 focus:outline-none`,
            ...(props.id ? { id: props.id } : {}),
            ...(props.ariaLabel ? { 'aria-label': props.ariaLabel } : {}),
            ...(props.error ? { 'aria-invalid': 'true' } : {}),
        },
    },
    onUpdate: ({ editor }) => {
        // `isEmpty` dipakai supaya dokumen kosong tersimpan sebagai string
        // kosong, bukan sebagai "<p></p>" — yang lolos validasi `required`.
        model.value = editor.isEmpty ? '' : editor.getHTML()
    },
})

// Isi yang datang dari luar (mis. reset form) dimuat ulang, tapi hanya kalau
// benar-benar berbeda — memanggil setContent pada tiap ketikan memindahkan
// kursor ke awal.
watch(model, (value) => {
    if (!editor.isDestroyed && value !== (editor.isEmpty ? '' : editor.getHTML())) {
        editor.commands.setContent(value, { emitUpdate: false })
    }
})

onBeforeUnmount(() => editor.destroy())

const BLOCKS = [
    { value: 'paragraph', label: t('editor.paragraph') },
    { value: 'h2', label: t('editor.heading_2') },
    { value: 'h3', label: t('editor.heading_3') },
]

const currentBlock = computed(() => {
    if (editor.isActive('heading', { level: 2 })) return 'h2'
    if (editor.isActive('heading', { level: 3 })) return 'h3'
    return 'paragraph'
})

function setBlock(value: string): void {
    if (value === 'paragraph') {
        editor.chain().focus().setParagraph().run()
        return
    }
    editor.chain().focus().toggleHeading({ level: value === 'h2' ? 2 : 3 }).run()
}

const ALL_MARKS = [
    { key: 'bold', icon: PhTextB, label: t('editor.bold'), run: () => editor.chain().focus().toggleBold().run() },
    { key: 'italic', icon: PhTextItalic, label: t('editor.italic'), run: () => editor.chain().focus().toggleItalic().run() },
    { key: 'underline', icon: PhTextUnderline, label: t('editor.underline'), run: () => editor.chain().focus().toggleUnderline().run() },
    { key: 'strike', icon: PhTextStrikethrough, label: t('editor.strike'), run: () => editor.chain().focus().toggleStrike().run() },
    { key: 'highlight', icon: PhHighlighterCircle, label: t('editor.highlight'), run: () => editor.chain().focus().toggleHighlight().run() },
]

// Sorot dibuang di `basic`: profil Purifier `legal` tidak mengizinkan `mark`,
// jadi tombolnya akan menghapus pekerjaannya sendiri saat disimpan.
const MARKS = isBasic ? ALL_MARKS.filter((m) => m.key !== 'highlight') : ALL_MARKS

const LISTS = [
    { key: 'bulletList', icon: PhListBullets, label: t('editor.bullet_list'), run: () => editor.chain().focus().toggleBulletList().run() },
    { key: 'orderedList', icon: PhListNumbers, label: t('editor.ordered_list'), run: () => editor.chain().focus().toggleOrderedList().run() },
]

/*
 * Menyisipkan gambar: unggah dulu, baru sisipkan URL-nya.
 *
 * Base64 sengaja DIMATIKAN di ekstensinya. Gambar 1 MB yang disisipkan sebagai
 * data URI berubah jadi ~1,4 MB teks di dalam kolom `body`, ikut terkirim di
 * setiap muat halaman, dan tidak bisa di-cache browser mana pun. Yang tersimpan
 * di sini cuma path-nya.
 */
const uploading = ref(false)
const uploadError = ref<string | null>(null)

async function pickImage(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]

    // Nilainya dikosongkan lebih dulu: tanpa itu, memilih berkas yang SAMA dua
    // kali berturut-turut tidak memicu `change` sama sekali.
    input.value = ''

    if (!file) return

    uploading.value = true
    uploadError.value = null

    try {
        const body = new FormData()
        body.append('image', file)

        const response = await fetch('/editor/images', {
            method: 'POST',
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name=csrf-token]')?.content ?? '',
            },
        })

        if (!response.ok) {
            const payload = await response.json().catch(() => null)
            // Galat validasi Laravel (422) sudah berisi kalimat yang bisa
            // dibaca; sisanya tidak, dan menampilkan "500" ke penulis artikel
            // tidak memberi tahu apa pun yang bisa ia lakukan.
            uploadError.value = payload?.errors?.image?.[0] ?? t('editor.upload_failed')
            return
        }

        const { url } = (await response.json()) as { url: string }

        editor.chain().focus().setImage({ src: url, alt: file.name }).run()
    } catch {
        uploadError.value = t('editor.upload_failed')
    } finally {
        uploading.value = false
    }
}

/** Baris kaki editor (`341:4805`) — hitungan kata dan perkiraan waktu baca. */
const stats = computed(() => {
    const text = editor.getText().trim()
    const words = text === '' ? 0 : text.split(/\s+/).length
    return { words, minutes: Math.max(1, Math.round(words / 265)) }
})
</script>

<template>
    <div class="flex flex-col gap-1">
        <div
            class="flex flex-col border bg-editor-0 shadow-editor"
            :class="error ? 'border-danger' : 'border-editor-30'"
        >
            <div class="flex flex-wrap items-stretch border-b border-editor-20">
                <!-- Pemilih judul hanya di mode penuh: blok halaman hukum
                     sudah punya judulnya sendiri di field terpisah. -->
                <div v-if="!isBasic" class="flex items-center border-r border-editor-20 px-3 py-2">
                    <label class="sr-only" :for="id ? `${id}-block` : undefined">{{ t('editor.block_style') }}</label>
                    <select
                        :id="id ? `${id}-block` : undefined"
                        :value="currentBlock"
                        class="cursor-pointer bg-transparent font-editor text-[16px] font-bold text-editor-60"
                        @change="setBlock(($event.target as HTMLSelectElement).value)"
                    >
                        <option v-for="block in BLOCKS" :key="block.value" :value="block.value">
                            {{ block.label }}
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2 border-r border-editor-20 p-2">
                    <button
                        v-for="mark in MARKS"
                        :key="mark.key"
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center transition-colors"
                        :class="editor.isActive(mark.key) ? 'bg-editor-20 text-editor-80' : 'text-editor-60 hover:text-editor-80'"
                        :aria-pressed="editor.isActive(mark.key)"
                        :aria-label="mark.label"
                        :title="mark.label"
                        @click="mark.run()"
                    >
                        <component :is="mark.icon" :size="20" aria-hidden="true" />
                    </button>
                </div>

                <div class="flex items-center gap-2 border-r border-editor-20 p-2">
                    <button
                        v-for="list in LISTS"
                        :key="list.key"
                        type="button"
                        class="flex size-8 cursor-pointer items-center justify-center transition-colors"
                        :class="editor.isActive(list.key) ? 'bg-editor-20 text-editor-80' : 'text-editor-60 hover:text-editor-80'"
                        :aria-pressed="editor.isActive(list.key)"
                        :aria-label="list.label"
                        :title="list.label"
                        @click="list.run()"
                    >
                        <component :is="list.icon" :size="20" aria-hidden="true" />
                    </button>
                </div>

                <!-- Sisipkan gambar hanya di mode penuh: profil Purifier
                     `legal` tidak mengizinkan `img`, jadi di mode dasar
                     tombolnya akan mengunggah berkas lalu membuang tagnya saat
                     disimpan — pekerjaan yang hilang tanpa satu pun pesan. -->
                <div v-if="!isBasic" class="flex items-center gap-2 p-2">
                    <label
                        class="flex size-8 items-center justify-center transition-colors"
                        :class="
                            uploading
                                ? 'cursor-wait text-editor-30'
                                : 'cursor-pointer text-editor-60 hover:text-editor-80'
                        "
                        :title="`${t('editor.insert_image')} — ${t('news.editor_image_hint')}`"
                    >
                        <component
                            :is="uploading ? PhSpinnerGap : PhImageSquare"
                            :size="20"
                            :class="uploading ? 'animate-spin motion-reduce:animate-none' : ''"
                            aria-hidden="true"
                        />
                        <span class="sr-only">{{ t('editor.insert_image') }}</span>
                        <input
                            type="file"
                            accept="image/webp"
                            class="sr-only"
                            :disabled="uploading"
                            @change="pickImage"
                        />
                    </label>
                </div>
            </div>

            <p
                v-if="uploadError"
                role="alert"
                class="border-b border-editor-20 bg-editor-5 px-4 py-2 text-body-xs text-danger"
            >
                {{ uploadError }}
            </p>

            <EditorContent :editor="editor" />

            <div class="flex items-center gap-2 border-t border-editor-20 bg-editor-5 px-4 py-3">
                <span class="font-editor text-[14px] font-semibold text-editor-60 tabular-nums">
                    Word Count: {{ stats.words.toLocaleString('id-ID') }}
                </span>
                <span class="size-1 rounded-full bg-editor-30" aria-hidden="true" />
                <span class="font-editor text-[14px] font-semibold text-editor-60">
                    Reading Time: ~{{ stats.minutes }}min
                </span>
            </div>
        </div>

        <p v-if="error" role="alert" class="text-body-xs text-danger">{{ error }}</p>
    </div>
</template>
