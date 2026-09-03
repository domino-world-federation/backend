<script setup lang="ts">
import { PhInfo, PhShieldWarning, PhWarningCircle } from '@phosphor-icons/vue'
import { computed, type Component } from 'vue'

/**
 * Pita keterangan di atas sebuah daftar atau di dalam formulir.
 *
 * Empat layar menggambarnya dengan bentuk yang sama — Admin Users
 * (`528:9743`), Audit Log (`528:11530`), Roles (`528:9744`), dan IP Whitelist
 * (`527:7869`) — jadi ia satu komponen. Disalin empat kali, jaraknya akan
 * menyimpang perlahan dan yang terasa bukan "yang satu salah" melainkan
 * "aplikasinya tidak rapi".
 *
 * Isinya SELALU kalimat yang mengubah cara orang membaca layarnya: apa yang
 * ditegakkan, apa yang tidak bisa dibatalkan, siapa yang boleh mengubah. Bukan
 * tempat untuk basa-basi sambutan — pita yang isinya tidak penting mengajari
 * orang melewatinya, dan sesudah itu yang penting pun ikut terlewat.
 */
const props = withDefaults(
    defineProps<{
        /** `info` biru-navy (bawaan), `warning` emas, `security` emas + perisai. */
        tone?: 'info' | 'warning' | 'security'
        /** Judul opsional — dipakai panel di dalam formulir, bukan pita di atas tabel. */
        title?: string
    }>(),
    { tone: 'info' },
)

const icon = computed<Component>(() => ({
    info: PhInfo,
    warning: PhWarningCircle,
    security: PhShieldWarning,
}[props.tone]))
</script>

<template>
    <div class="flex items-start gap-2 border border-primary-60 bg-primary-60/10 px-3 py-2.5">
        <component :is="icon" :size="20" class="mt-px shrink-0 text-primary-90" aria-hidden="true" />

        <div class="flex min-w-0 flex-col gap-1">
            <h3 v-if="title" class="text-heading-6 text-cool-90">{{ title }}</h3>
            <!-- `whitespace-pre-line`: sebagian catatan datang sebagai daftar
                 berpoin dalam satu string terjemahan (lihat panel "Validation &
                 Security"), dan barisnya harus tetap terpisah. -->
            <p class="text-body-s whitespace-pre-line text-primary-90" :class="title ? 'text-cool-60' : ''">
                <slot />
            </p>
        </div>
    </div>
</template>
