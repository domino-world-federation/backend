<script setup lang="ts">
/**
 * `Controls` Type=Toggle (Figma `252:1679` aktif / `252:1680` mati).
 *
 * Track 32×16 di dalam kotak sentuh 32×20, knob 12, aktif Primary/60.
 * Dibangun di atas `<button role="switch">`, bukan `<div>` ber-`@click`:
 * spasi/enter dan pengumuman on/off datang gratis dari platform.
 */
withDefaults(
    defineProps<{
        modelValue: boolean
        label?: string
        id?: string
        disabled?: boolean
        /**
         * Sembunyikan labelnya dari layar, tapi TETAP bacakan.
         *
         * Untuk toggle di dalam sel tabel. Di sana nama barisnya sudah tercetak
         * di kolom pertama dan judul kolomnya sudah mengatakan apa yang
         * disetel, jadi mencetak "Highlight Scheduled: 2026 qualification
         * calendar" di samping sakelarnya cuma mengulang dua hal yang sudah
         * terbaca. Pembaca layar TIDAK punya kedua konteks itu — kursornya
         * mendarat langsung di sakelarnya — jadi teksnya tidak dihapus, cuma
         * dipindah ke `aria-label`.
         */
        hideLabel?: boolean
    }>(),
    { disabled: false, hideLabel: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            :id="id"
            type="button"
            role="switch"
            :aria-checked="modelValue"
            :aria-label="label"
            :disabled="disabled"
            class="flex h-5 w-8 items-center disabled:cursor-not-allowed disabled:opacity-50"
            @click="emit('update:modelValue', !modelValue)"
        >
            <span
                class="flex h-4 w-8 items-center rounded-full p-0.5 transition-colors"
                :class="modelValue ? 'bg-primary-60 justify-end' : 'bg-cool-60 justify-start'"
            >
                <span class="block size-3 rounded-full bg-white" />
            </span>
        </button>

        <span v-if="label && !hideLabel" class="text-body-s text-cool-60">{{ label }}</span>
    </div>
</template>
