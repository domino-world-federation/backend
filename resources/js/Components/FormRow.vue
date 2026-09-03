<script setup lang="ts">
import { useId } from 'vue'

/**
 * Pola baris form yang berulang di seluruh wireframe: label + deskripsi kecil
 * di kiri, kontrolnya di kanan, gap 32.
 *
 * Contoh sumbernya `258:8217` (Contact & Social), `341:4775` (Add FAQ),
 * `262:3469` (Add Press Releases) — bentuknya identik di ketiganya.
 *
 * Id-nya lahir di sini dan diserahkan ke slot supaya `<label for>` benar-benar
 * menunjuk ke kontrolnya. Di Figma label itu cuma teks; teks saja berarti klik
 * label tidak memfokuskan field dan pembaca layar membacakan input tanpa nama.
 */
withDefaults(
    defineProps<{
        label: string
        description?: string
        required?: boolean
        /**
         * Kontrol yang lebarnya mengikuti isi (toggle, radio, unggahan) —
         * bukan field yang harus memenuhi lebar.
         *
         * Ia HANYA memengaruhi kolom kanan. Kolom labelnya tetap 280 di semua
         * baris: itulah yang membuat seluruh kontrol di satu kartu berdiri di
         * garis yang sama.
         */
        compact?: boolean
    }>(),
    { required: false, compact: false },
)

const id = useId()
</script>

<template>
    <div class="flex w-full items-start gap-8">
        <div class="flex w-[280px] shrink-0 flex-col gap-2">
            <label :for="id" class="text-body-s text-cool-70">
                {{ label }}<span v-if="required" class="text-danger"> *</span>
            </label>
            <p v-if="description" class="text-body-xs text-cool-60">{{ description }}</p>
        </div>

        <div class="min-w-0" :class="compact ? '' : 'flex-1'">
            <slot :id="id" />
        </div>
    </div>
</template>
