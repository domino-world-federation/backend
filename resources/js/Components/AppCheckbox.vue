<script setup lang="ts">
import { useId } from 'vue'
import { PhCheck } from '@phosphor-icons/vue'

/**
 * `Controls` Type=Checkbox (Figma `252:1670` / `252:1669`) — kotak 16 di dalam
 * area 20, terisi Primary/60 saat dipilih.
 *
 * `<input type="checkbox">` sungguhan yang disembunyikan secara visual, bukan
 * div: form submit, autofill, dan pembaca layar semuanya bergantung padanya.
 */
withDefaults(
    defineProps<{
        modelValue: boolean
        label?: string
        /** Teks abu di kanan label, mis. "(1/3)" di layar Add FAQ. */
        hint?: string
        disabled?: boolean
        /**
         * Sembunyikan label secara VISUAL, bukan hapus.
         *
         * Untuk kotak di dalam matriks izin: kolomnya sudah diberi judul, tapi
         * pembaca layar yang melompat langsung ke sebuah kotak tetap butuh
         * tahu itu kotak apa. `sr-only`, bukan `aria-label` kosong.
         */
        hideLabel?: boolean
    }>(),
    { disabled: false, hideLabel: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>()

const id = useId()
</script>

<template>
    <div class="flex items-center gap-2" :class="hideLabel ? 'justify-center' : ''">
        <span class="relative flex size-5 items-center justify-center">
            <input
                :id="id"
                type="checkbox"
                class="peer absolute size-5 cursor-pointer opacity-0 disabled:cursor-not-allowed"
                :checked="modelValue"
                :disabled="disabled"
                @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
            />
            <span
                class="pointer-events-none flex size-4 items-center justify-center border transition-colors"
                :class="
                    modelValue
                        ? 'border-primary-60 bg-primary-60'
                        : 'border-cool-100 bg-transparent'
                "
            >
                <PhCheck v-if="modelValue" :size="10" weight="bold" class="text-white" />
            </span>
        </span>

        <label
            v-if="label"
            :for="id"
            class="flex items-center gap-1 text-body-s text-cool-60"
            :class="hideLabel ? 'sr-only' : ''"
        >
            {{ label }}
            <span v-if="hint" class="text-cool-60">{{ hint }}</span>
        </label>
    </div>
</template>
