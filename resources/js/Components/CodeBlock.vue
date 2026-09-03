<script setup lang="ts">
import { ref, onBeforeUnmount } from 'vue'
import { PhCheck, PhCopy } from '@phosphor-icons/vue'

/**
 * Blok kode dengan tombol salin — dipakai halaman Design System.
 *
 * Tidak ada penyorotan sintaks: menambah highlighter berarti menyeret satu
 * paket lagi ke dalam bundel setiap halaman demi satu halaman internal.
 */
defineProps<{ code: string }>()

const copied = ref(false)
const failed = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

onBeforeUnmount(() => clearTimeout(timer))

async function copy(code: string): Promise<void> {
    clearTimeout(timer)

    try {
        // `navigator.clipboard` tidak ada di konteks non-secure (http:// selain
        // localhost). Di situ tombolnya mengaku gagal, bukan diam-diam tidak
        // melakukan apa-apa.
        await navigator.clipboard.writeText(code)
        copied.value = true
        failed.value = false
    } catch {
        copied.value = false
        failed.value = true
    }

    timer = setTimeout(() => {
        copied.value = false
        failed.value = false
    }, 2000)
}
</script>

<template>
    <div class="relative">
        <pre
            class="overflow-x-auto border border-editor-20 bg-editor-5 p-4 text-editor-80"
        ><code class="font-mono text-[13px] leading-relaxed">{{ code }}</code></pre>

        <button
            type="button"
            class="absolute top-2 right-2 flex cursor-pointer items-center gap-1.5 border border-editor-20 bg-editor-0 px-2 py-1 text-button-s text-editor-60 transition-colors hover:text-editor-80"
            @click="copy(code)"
        >
            <component
                :is="copied ? PhCheck : PhCopy"
                :size="16"
                aria-hidden="true"
                :class="copied ? 'text-primary-60' : ''"
            />
            <span>{{ copied ? 'Tersalin' : failed ? 'Gagal — salin manual' : 'Salin' }}</span>
        </button>

        <!-- Diumumkan pembaca layar tanpa memindahkan fokus. -->
        <span class="sr-only" role="status">
            {{ copied ? 'Kode tersalin ke papan klip' : '' }}
        </span>
    </div>
</template>
