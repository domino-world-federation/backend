<script setup lang="ts">
import CodeBlock from '@/Components/CodeBlock.vue'

/**
 * Satu contoh di halaman Design System: judul, keterangan singkat, tampilan
 * hidup, lalu kodenya.
 *
 * Contoh hidup dan kode sengaja bersebelahan dan bukan hasil generate satu
 * sama lain — kalau kodenya digenerate dari komponen, ia akan selalu benar tapi
 * tidak pernah menunjukkan cara memakainya; kalau komponennya dirender dari
 * string kode, halaman ini butuh compiler runtime. Konsekuensinya keduanya bisa
 * berpisah, jadi ubah dua-duanya saat mengubah contoh.
 */
defineProps<{ title: string; description?: string; code: string }>()
</script>

<template>
    <section class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
            <h3 class="text-subtitle-s text-cool-90">{{ title }}</h3>
            <p v-if="description" class="text-body-xs text-cool-60">{{ description }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-4 border border-cool-20 bg-surface p-6">
            <slot />
        </div>

        <CodeBlock :code="code" />
    </section>
</template>
