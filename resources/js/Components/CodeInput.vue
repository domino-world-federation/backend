<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Enam kotak untuk kode TOTP (`2FA GAUTH 1`).
 *
 * Terlihat seperti enam kotak, tapi berperilaku seperti SATU field:
 * - menempel kode enam digit dari clipboard mengisi keenamnya sekaligus —
 *   ini cara paling umum orang memasukkannya, dan input yang hanya menerima
 *   satu karakter per kotak akan membuang lima digit tanpa memberi tahu;
 * - Backspace di kotak kosong mundur ke kotak sebelumnya;
 * - panah kiri/kanan berpindah;
 * - kotak terisi otomatis maju.
 *
 * `inputmode="numeric"` memunculkan papan angka di ponsel, dan
 * `autocomplete="one-time-code"` membuat iOS/Android menawarkan kode dari
 * notifikasi.
 */
const props = withDefaults(defineProps<{ length?: number; error?: string; autofocus?: boolean }>(), {
    length: 6,
    autofocus: true,
})

const model = defineModel<string>({ default: '' })

const { t } = useI18n()

const boxes = ref<HTMLInputElement[]>([])
const digits = ref<string[]>(Array.from({ length: props.length }, () => ''))

/** Kode dari luar (mis. reset setelah gagal) disebar ulang ke kotaknya. */
watch(model, (value) => {
    if (value === digits.value.join('')) return
    const chars = value.slice(0, props.length).split('')
    digits.value = Array.from({ length: props.length }, (_, i) => chars[i] ?? '')
})

function push(): void {
    model.value = digits.value.join('')
}

function focus(index: number): void {
    nextTick(() => boxes.value[index]?.focus())
}

function onInput(index: number, event: Event): void {
    const raw = (event.target as HTMLInputElement).value.replace(/\D/g, '')

    if (raw.length > 1) {
        // Beberapa digit sekaligus — biasanya autofill kode SMS/OTP, yang
        // memasukkan seluruh kode ke kotak pertama.
        spread(raw, index)
        return
    }

    digits.value[index] = raw
    push()

    if (raw && index < props.length - 1) focus(index + 1)
}

function onKeydown(index: number, event: KeyboardEvent): void {
    if (event.key === 'Backspace' && !digits.value[index] && index > 0) {
        event.preventDefault()
        digits.value[index - 1] = ''
        push()
        focus(index - 1)
        return
    }

    if (event.key === 'ArrowLeft' && index > 0) {
        event.preventDefault()
        focus(index - 1)
    }

    if (event.key === 'ArrowRight' && index < props.length - 1) {
        event.preventDefault()
        focus(index + 1)
    }
}

function onPaste(index: number, event: ClipboardEvent): void {
    const text = event.clipboardData?.getData('text') ?? ''
    if (!text) return

    event.preventDefault()
    spread(text.replace(/\D/g, ''), index)
}

function spread(value: string, from: number): void {
    const chars = value.slice(0, props.length - from).split('')
    chars.forEach((char, offset) => (digits.value[from + offset] = char))
    push()
    focus(Math.min(from + chars.length, props.length - 1))
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex items-center justify-center gap-2" role="group" :aria-label="t('two_factor.code_label')">
            <input
                v-for="(digit, index) in digits"
                :key="index"
                :ref="(el) => (boxes[index] = el as HTMLInputElement)"
                :value="digit"
                type="text"
                inputmode="numeric"
                autocomplete="one-time-code"
                maxlength="1"
                :autofocus="autofocus && index === 0"
                :aria-label="t('two_factor.digit_label', { number: index + 1 })"
                :aria-invalid="error ? 'true' : undefined"
                class="size-11 border text-center text-heading-6 text-cool-90 tabular-nums"
                :class="error ? 'border-danger bg-surface' : 'border-cool-30 bg-cool-10'"
                @input="onInput(index, $event)"
                @keydown="onKeydown(index, $event)"
                @paste="onPaste(index, $event)"
                @focus="($event.target as HTMLInputElement).select()"
            />
        </div>

        <p v-if="error" role="alert" class="text-center text-body-xs text-danger">{{ error }}</p>
    </div>
</template>
