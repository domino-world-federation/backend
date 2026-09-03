<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { PhCheck, PhTranslate } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Pengalih bahasa di topbar.
 *
 * Menu, bukan tombol dua keadaan: dua bahasa hari ini, tapi tombol yang
 * membalik antara ID dan EN harus ditulis ulang jadi menu begitu bahasa ketiga
 * datang. Menunya sudah benar sejak sekarang dan biayanya sama.
 *
 * Kode bahasa dicetak di sebelah ikon ("ID"/"EN"). Ikon globe sendirian tidak
 * memberi tahu bahasa apa yang sedang aktif — dan itu justru yang ingin
 * diketahui orang sebelum mengklik.
 */
const { t, locale, locales } = useI18n()

const open = ref(false)
const root = ref<HTMLElement | null>(null)

function close(): void {
    open.value = false
}

function choose(value: string): void {
    close()
    if (value === locale.value) return

    // `preserveScroll` supaya halaman tidak melompat ke atas saat bahasanya
    // berganti — orang biasanya menggantinya di tengah membaca sesuatu.
    router.put('/locale', { locale: value }, { preserveScroll: true })
}

function onDocumentClick(event: MouseEvent): void {
    if (open.value && root.value && !root.value.contains(event.target as Node)) close()
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') close()
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick)
    document.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick)
    document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex cursor-pointer items-center gap-1.5 px-1.5 py-1 text-cool-90"
            :aria-label="t('nav.language')"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="open = !open"
        >
            <PhTranslate :size="24" aria-hidden="true" />
            <span class="text-body-xs font-medium uppercase">
                {{ locales.find((l) => l.value === locale)?.short ?? locale }}
            </span>
        </button>

        <div
            v-if="open"
            role="menu"
            class="absolute top-full right-0 z-30 mt-1 min-w-[180px] border border-cool-20 bg-surface py-1 shadow-editor"
        >
            <button
                v-for="option in locales"
                :key="option.value"
                type="button"
                role="menuitemradio"
                :aria-checked="option.value === locale"
                class="flex w-full cursor-pointer items-center gap-2 px-4 py-2 text-left text-body-s text-cool-90 hover:bg-cool-10"
                @click="choose(option.value)"
            >
                <PhCheck
                    :size="16"
                    aria-hidden="true"
                    :class="option.value === locale ? 'text-primary-60' : 'invisible'"
                />
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
