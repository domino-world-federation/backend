<script setup lang="ts">
import { computed, type Component } from 'vue'
import {
    PhAddressBook,
    PhCalendarBlank,
    PhChat,
    PhEnvelope,
    PhPaperPlaneTilt,
    PhShieldWarning,
    PhFile,
    PhFolder,
    PhImages,
    PhNewspaper,
    PhClockCounterClockwise,
    PhPalette,
    PhQuestion,
    PhScroll,
    PhShieldCheck,
    PhShieldStar,
    PhSquaresFour,
    PhStack,
    PhTrophy,
    PhUserCircle,
    PhUsersThree,
    PhUsers,
} from '@phosphor-icons/vue'

/**
 * Menerjemahkan nama ikon dari `App\Support\Navigation` ke komponen Phosphor.
 *
 * Peta eksplisit, bukan impor dinamis dengan nama yang dirangkai: dengan peta
 * ini, nama ikon yang salah ketik di PHP muncul sebagai kotak kosong yang
 * terlihat saat review — sementara impor dinamis akan memuat seluruh 9.000 ikon
 * Phosphor ke dalam bundel demi 14 yang benar-benar dipakai.
 */
const props = defineProps<{ name?: string; size?: number }>()

const ICONS: Record<string, Component> = {
    SquaresFour: PhSquaresFour,
    Chat: PhChat,
    CalendarBlank: PhCalendarBlank,
    Folder: PhFolder,
    File: PhFile,
    Users: PhUsers,
    UserCircle: PhUserCircle,
    Stack: PhStack,
    Trophy: PhTrophy,
    Question: PhQuestion,
    Newspaper: PhNewspaper,
    Images: PhImages,
    AddressBook: PhAddressBook,
    Envelope: PhEnvelope,
    PaperPlaneTilt: PhPaperPlaneTilt,
    ShieldWarning: PhShieldWarning,
    Scroll: PhScroll,
    Palette: PhPalette,
    UsersThree: PhUsersThree,
    ClockCounterClockwise: PhClockCounterClockwise,
    ShieldCheck: PhShieldCheck,
    ShieldStar: PhShieldStar,
}

const icon = computed(() => (props.name ? ICONS[props.name] : undefined))
</script>

<template>
    <component :is="icon" v-if="icon" :size="size ?? 24" aria-hidden="true" />
</template>
