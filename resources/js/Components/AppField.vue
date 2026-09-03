<script setup lang="ts">
import { computed, useId } from 'vue'

/**
 * Component set Figma `Field` — State=Default|Disabled, Textarea=false|true.
 *
 * Bentuknya Carbon: latar CoolGray/10, tanpa garis kecuali di bawah, tinggi 48
 * untuk input satu baris dan 96 untuk textarea.
 */
const props = withDefaults(
    defineProps<{
        modelValue: string | number | null
        /** Diberikan `FormRow` agar `<label for>`-nya benar-benar menunjuk ke sini. */
        id?: string
        type?: string
        textarea?: boolean
        placeholder?: string
        disabled?: boolean
        error?: string
        autocomplete?: string
        required?: boolean
    }>(),
    { type: 'text', textarea: false, disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

/*
 * Atribut yang tidak dikenali diteruskan ke KONTROLNYA, bukan ke `<div>`
 * pembungkusnya.
 *
 * Bawaannya sebaliknya, dan itu gagal tanpa suara: `aria-label` mendarat di
 * sebuah div (pembaca layar tetap membacakan input tanpa nama) dan `autofocus`
 * di elemen yang tidak bisa difokuskan (tidak terjadi apa-apa). Keduanya sudah
 * telanjur dipakai di beberapa layar sebelum ini ketahuan.
 */
defineOptions({ inheritAttrs: false })

const fallbackId = useId()
const id = computed(() => props.id ?? fallbackId)
const errorId = computed(() => (props.error ? `${id.value}-error` : undefined))

const classes = computed(() => [
    'w-full bg-cool-10 text-body-m text-cool-90 placeholder:text-cool-60',
    'border-b transition-colors outline-none',
    props.error ? 'border-danger' : 'border-cool-30 focus:border-cool-90',
    props.disabled ? 'cursor-not-allowed text-cool-40' : '',
    props.textarea ? 'h-24 resize-y px-4 py-3.5' : 'h-12 px-4 py-3',
])
</script>

<template>
    <div class="flex w-full flex-col gap-1">
        <textarea
            v-if="textarea"
            :id="id"
            v-bind="$attrs"
            :class="classes"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="errorId"
            @input="emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
        />
        <input
            v-else
            :id="id"
            v-bind="$attrs"
            :class="classes"
            :type="type"
            :value="modelValue ?? ''"
            :placeholder="placeholder"
            :disabled="disabled"
            :required="required"
            :autocomplete="autocomplete"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="errorId"
            @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
        />

        <!-- `role="alert"` supaya pembaca layar mengumumkan galat validasi yang
             muncul setelah submit, bukan menunggu fokus kembali ke field. -->
        <p v-if="error" :id="errorId" role="alert" class="text-body-xs text-danger">
            {{ error }}
        </p>
    </div>
</template>
