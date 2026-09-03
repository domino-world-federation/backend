<script setup lang="ts">
/**
 * Kartu putih pembungkus tiap blok form — Figma `252:1476`, `258:8214`,
 * `341:4772`: padding 24, `Heading/6` di atas, gap 24 antar isi.
 */
defineProps<{ title?: string }>()
</script>

<template>
    <section class="flex w-full flex-col gap-5 bg-surface p-6">
        <div v-if="title || $slots.header" class="flex items-center justify-between gap-4">
            <h2 v-if="title" class="text-heading-6 text-cool-90">{{ title }}</h2>
            <slot name="header" />
        </div>

        <div class="flex w-full flex-col gap-6">
            <slot />
        </div>
    </section>
</template>
