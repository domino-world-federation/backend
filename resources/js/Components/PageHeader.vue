<script setup lang="ts">
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import type { Crumb } from '@/types'

/**
 * Kepala halaman yang berulang di tiap layar: breadcrumb, `Heading/4`, lalu
 * tombol-tombol di kanan (Export / Add …) — Figma `252:2139`, `433:6125`.
 *
 * Jarak breadcrumb ke judul 12px, bukan 24px seperti di Figma: breadcrumb dan
 * judul adalah satu kesatuan — keduanya menjawab "saya di mana" — dan jarak
 * yang sama besar dengan jarak antar-blok membuat keduanya terbaca sebagai dua
 * blok terpisah.
 */
defineProps<{ title: string; breadcrumbs?: Crumb[] }>()
</script>

<template>
    <div class="flex flex-col gap-3">
        <Breadcrumbs v-if="breadcrumbs?.length" :items="breadcrumbs" />

        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-2">
                <h1 class="text-heading-4 text-cool-90">{{ title }}</h1>
                <p v-if="$slots.description" class="text-body-s text-cool-60">
                    <slot name="description" />
                </p>
            </div>

            <div v-if="$slots.actions" class="flex shrink-0 items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
