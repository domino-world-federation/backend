<script setup lang="ts">
import { useId } from 'vue'

/**
 * `Controls` Type=Radio (Figma `252:1675` / `252:1676`) — lingkaran 16 dengan
 * cincin Primary/60, titik 8 di tengah saat dipilih.
 *
 * Dipakai di "Select Type: Event / Tournament" dan "Asset: Image / Video"
 * (layar Add Gallery, `396:6004` dan `403:6116`).
 */
const props = withDefaults(
    defineProps<{
        modelValue: string
        value: string
        name: string
        label?: string
        disabled?: boolean
    }>(),
    { disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const id = useId()
</script>

<template>
    <div class="flex items-center gap-2">
        <span class="relative flex size-5 items-center justify-center">
            <input
                :id="id"
                type="radio"
                class="absolute size-5 cursor-pointer opacity-0 disabled:cursor-not-allowed"
                :name="name"
                :value="value"
                :checked="modelValue === value"
                :disabled="disabled"
                @change="emit('update:modelValue', props.value)"
            />
            <span
                class="pointer-events-none flex size-4 items-center justify-center rounded-full border border-primary-60"
            >
                <span v-if="modelValue === value" class="block size-2 rounded-full bg-primary-60" />
            </span>
        </span>

        <label v-if="label" :for="id" class="text-body-s text-cool-60">{{ label }}</label>
    </div>
</template>
