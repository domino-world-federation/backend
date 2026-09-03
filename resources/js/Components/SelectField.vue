<script setup lang="ts">
import { PhCaretDown } from '@phosphor-icons/vue'

/**
 * Field pilihan. Membungkus `<select>` asli, bukan menggantinya dengan daftar
 * buatan sendiri: yang asli sudah membawa navigasi keyboard, pencarian ketik,
 * dan tampilan asli sistem di ponsel.
 *
 * Chevron-nya digambar sendiri karena panah bawaan tiap sistem berbeda-beda
 * dan tidak ada yang cocok dengan Figma; `appearance-none` mematikan yang asli.
 */
withDefaults(
    defineProps<{
        id?: string
        options?: Array<{ value: string | number; label: string }>
        /**
         * Pilihan yang dikelompokkan (`<optgroup>`). Kalau diisi, `options`
         * diabaikan. Dipakai saat nama kelompoknya ikut menjawab pertanyaan
         * yang sedang dijawab — memilih FAQ, misalnya, dilakukan orang lewat
         * kategorinya.
         */
        groups?: Array<{ label: string; options: Array<{ value: string | number; label: string }> }>
        placeholder?: string
        disabled?: boolean
        error?: string
    }>(),
    { placeholder: 'Select', disabled: false },
)

const model = defineModel<string | number | null>({ default: null })
</script>

<template>
    <div class="flex w-full flex-col gap-1">
        <div class="relative">
            <select
                :id="id"
                v-model="model"
                :disabled="disabled"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error && id ? `${id}-error` : undefined"
                class="h-12 w-full appearance-none border-b bg-cool-10 px-4 pr-11 text-body-m text-cool-90 disabled:cursor-not-allowed disabled:text-cool-40"
                :class="error ? 'border-danger' : 'border-cool-30'"
            >
                <option :value="null" disabled>{{ placeholder }}</option>

                <template v-if="groups">
                    <optgroup v-for="group in groups" :key="group.label" :label="group.label">
                        <option
                            v-for="option in group.options"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </optgroup>
                </template>

                <template v-else>
                    <option v-for="option in options ?? []" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </template>
            </select>

            <PhCaretDown
                :size="24"
                class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-cool-60"
                aria-hidden="true"
            />
        </div>

        <p v-if="error" :id="id ? `${id}-error` : undefined" role="alert" class="text-body-xs text-danger">
            {{ error }}
        </p>
    </div>
</template>
