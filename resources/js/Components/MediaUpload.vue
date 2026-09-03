<script setup lang="ts">
import { computed, ref } from 'vue'
import { PhFileArrowUp, PhImageSquare, PhX } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Kotak unggah 101×101 (`366:5166`, `401:6096`).
 *
 * Satu komponen untuk gambar, video, dan PDF — yang berbeda cuma ikon,
 * pratinjau, dan daftar mime-nya. Tiga komponen kembar akan berbeda perlahan.
 *
 * `URL.createObjectURL` selalu dipasangkan dengan `revokeObjectURL`: tanpa itu
 * tiap kali orang mengganti pilihan gambarnya, blob lamanya menetap di memori
 * sampai halaman ditutup.
 */
const props = withDefaults(
    defineProps<{
        id?: string
        kind?: 'image' | 'video' | 'document'
        /** URL berkas yang sudah tersimpan, untuk mode ubah. */
        existingUrl?: string | null
        existingLabel?: string | null
        accept?: string
        error?: string
    }>(),
    { kind: 'image', existingUrl: null, existingLabel: null },
)

const { t } = useI18n()

const model = defineModel<File | null>({ default: null })

const preview = ref<string | null>(null)
const input = ref<HTMLInputElement | null>(null)

const accepts = computed(
    () =>
        props.accept ??
        // WebP saja untuk gambar — sama dengan `dwf.uploads.image_mimes` di
        // server. Ini cuma saringan di dialog berkas, BUKAN penjagaan: yang
        // menolak sungguhan tetap aturan validasi, karena `accept` bisa
        // dilewati dengan memilih "Semua berkas".
        { image: 'image/webp', video: 'video/mp4,video/webm', document: 'application/pdf' }[
            props.kind
        ],
)

function release(): void {
    if (preview.value) {
        URL.revokeObjectURL(preview.value)
        preview.value = null
    }
}

function onPick(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null
    release()

    model.value = file
    if (file && props.kind === 'image') {
        preview.value = URL.createObjectURL(file)
    }
}

function clear(): void {
    release()
    model.value = null
    if (input.value) input.value.value = ''
}

const shownImage = computed(() => preview.value ?? (props.kind === 'image' ? props.existingUrl : null))
const fileName = computed(() => model.value?.name ?? props.existingLabel)
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center gap-3">
            <label
                :for="id"
                class="relative flex size-[101px] cursor-pointer items-center justify-center overflow-hidden border border-cool-30 bg-surface transition-colors hover:border-cool-60"
                :class="error ? 'border-danger' : ''"
            >
                <img
                    v-if="shownImage"
                    :src="shownImage"
                    alt=""
                    class="size-full object-cover"
                />
                <component
                    :is="kind === 'document' ? PhFileArrowUp : PhImageSquare"
                    v-else
                    :size="40"
                    class="text-cool-60"
                    aria-hidden="true"
                />

                <input
                    :id="id"
                    ref="input"
                    type="file"
                    :accept="accepts"
                    class="sr-only"
                    :aria-invalid="error ? 'true' : undefined"
                    @change="onPick"
                />
            </label>

            <div v-if="fileName" class="flex min-w-0 flex-col gap-1">
                <span class="truncate text-body-xs text-cool-70">{{ fileName }}</span>
                <button
                    type="button"
                    class="flex w-fit cursor-pointer items-center gap-1 text-body-xs text-cool-60 hover:text-danger"
                    @click="clear"
                >
                    <PhX :size="14" aria-hidden="true" />
                    {{ t('common.remove_selection') }}
                </button>
            </div>
        </div>

        <p v-if="error" role="alert" class="text-body-xs text-danger">{{ error }}</p>
    </div>
</template>
