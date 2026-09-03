<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'

/**
 * Component set Figma `button` — Style=Filled|Outline|Link, Size=M|S.
 *
 * Merender `<button>`, `<a>`, atau `<Link>` Inertia tergantung prop `href`:
 * tombol yang sebenarnya menavigasi harus jadi tautan, kalau tidak keyboard
 * dan klik-tengah tidak bekerja seperti yang dikira orang.
 */
const props = withDefaults(
    defineProps<{
        variant?: 'filled' | 'outline' | 'link'
        size?: 'm' | 's'
        href?: string
        /** Paksa `<a>` biasa, bukan kunjungan Inertia (unduhan, tautan luar). */
        external?: boolean
        type?: 'button' | 'submit'
        disabled?: boolean
    }>(),
    { variant: 'filled', size: 'm', type: 'button', disabled: false, external: false },
)

const component = computed(() => {
    if (props.href === undefined) return 'button'
    return props.external ? 'a' : Link
})

/*
 * Umpan balik tekan di sini memakai transisi CSS, BUKAN `motion-v` seperti
 * `IconButton`.
 *
 * Alasannya bukan selera: komponen ini polimorfik — ia bisa jadi `<button>`,
 * `<a>`, atau `<Link>` Inertia tergantung propnya, dan ia yang menanggung
 * seluruh tombol submit di aplikasi. Membungkusnya dengan `<Motion :as>` berarti
 * setiap atribut (`type="submit"`, `href`, `disabled`) menempuh satu lapis
 * penerusan tambahan, dan yang rusak kalau lapisan itu meleset adalah pengiriman
 * formulir — mahal untuk ditukar dengan pegas. Transisi CSS pada `transform`
 * berjalan di compositor yang sama dan terlihat sama halusnya di gerak sependek
 * ini.
 *
 * `motion-reduce:` wajib ikut: berbeda dari `motion-v`, CSS tidak menghormati
 * "Reduce Motion" dengan sendirinya.
 */
const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 select-none',
    'transition-[color,background-color,border-color,transform] duration-150 ease-out',
    'motion-reduce:transition-colors',
    props.size === 'm' ? 'h-12 px-3 text-button-m' : 'h-10 px-3 text-button-s',
    {
        filled: 'bg-cool-100 text-on-inverse hover:bg-cool-90 disabled:bg-cool-40',
        outline: 'border-2 border-cool-90 bg-surface text-cool-100 hover:bg-cool-10 disabled:border-cool-30 disabled:text-cool-40',
        link: 'bg-transparent text-cool-100 hover:text-primary-90 disabled:text-cool-40',
    }[props.variant],
    props.disabled
        ? 'pointer-events-none opacity-60'
        : 'cursor-pointer active:scale-[0.97] motion-reduce:active:scale-100',
])
</script>

<template>
    <component
        :is="component"
        :class="classes"
        :href="href"
        :type="href === undefined ? type : undefined"
        :disabled="href === undefined ? disabled : undefined"
        :aria-disabled="href !== undefined && disabled ? 'true' : undefined"
    >
        <slot name="iconLeft" />
        <span v-if="$slots.default" class="px-4"><slot /></span>
        <slot name="iconRight" />
    </component>
</template>
