<script setup lang="ts">
/**
 * Satu balok abu berdenyut. Ukurannya diatur pemanggil lewat kelas
 * (`class="h-4 w-40"`) — kelas fallthrough digabung dengan yang di sini.
 *
 * `motion-reduce:animate-none` bukan hiasan: denyut yang berulang tanpa henti
 * adalah persis jenis gerakan yang dimatikan orang lewat "Reduce Motion", dan
 * `animate-pulse` Tailwind tidak menghormatinya sendiri.
 *
 * `aria-hidden`: bentuk ini tidak punya makna untuk dibacakan. Yang mengumumkan
 * keadaan memuat adalah wadahnya (lihat `SkeletonTable`), bukan tiap balok.
 */
withDefaults(defineProps<{ circle?: boolean }>(), { circle: false })
</script>

<template>
    <span
        class="block animate-pulse bg-cool-20 motion-reduce:animate-none"
        :class="circle ? 'rounded-full' : 'rounded-xs'"
        aria-hidden="true"
    />
</template>
