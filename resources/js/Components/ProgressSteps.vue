<script setup lang="ts">
import { computed } from 'vue'

/**
 * Sidebar Progress Step — Figma `585:11561`, komponen `Atom/Progress-step`
 * (`585:11529`) dan `Atom/Progress-indicator-step` (`585:11538`).
 *
 * Bukan sekadar daftar tautan: tiap langkah menggambar CINCIN yang menunjukkan
 * seberapa banyak section itu sudah terisi. Desainnya memberi tiga contoh
 * varian — `Step=0%`, `Step=25%`, `Step=50%` — jadi angkanya memang pecahan,
 * bukan hanya "selesai / belum".
 *
 * Ukurannya dari desain: baris 218px dengan gap 8, ikon 20×20, garis
 * penghubung setinggi 25 dan tebal 1 (CoolGray/20), judul Body/S CoolGray/80.
 * Kartunya putih dengan garis CoolGray/10, sudut 4, dan bayangan halus.
 *
 * Langkah TERAKHIR memakai varian `Position=End` (`585:11534`) — tanpa garis
 * penghubung. Garis yang menggantung di bawah langkah terakhir menjanjikan
 * langkah berikutnya yang tidak ada.
 */
const props = defineProps<{
    steps: Array<{
        /** Dipakai sebagai `#section-{id}` untuk melompat ke bagiannya. */
        id: string
        label: string
        /** 0..1 — bagian section yang sudah terisi. */
        progress: number
    }>
    /** Judul kecil di atas daftar; tidak digambar desain, jadi opsional. */
    label?: string
}>()

/*
 * Cincinnya SVG, bukan tiga berkas gambar terpisah.
 *
 * Desain menyediakan varian 0/25/50 sebagai contoh, tapi progres yang
 * sebenarnya bisa jatuh di mana saja (tiga dari tujuh field = 43%). Membulatkan
 * ke salah satu dari tiga varian akan membuat cincin berhenti bergerak justru
 * saat orang sedang mengisi.
 */
const RADIUS = 8
const CIRCUMFERENCE = 2 * Math.PI * RADIUS

/**
 * Melompat ke sectionnya TANPA menyentuh riwayat.
 *
 * Sebelumnya ini tautan `#section-…` biasa, dan itu salah dua kali:
 *
 * 1. Navigasi fragmen dihitung browser sebagai perpindahan entri riwayat, jadi
 *    `popstate` menyala — dan penjaga "perubahan belum disimpan" membacanya
 *    sebagai kepergian. Menekan satu langkah memunculkan dialog "Leave without
 *    saving?" padahal halamannya tidak ke mana-mana.
 * 2. Delapan langkah berarti delapan entri riwayat. Sesudah mengisi formulir,
 *    menekan Back tidak membawa orang keluar melainkan menyusuri hash satu per
 *    satu — tombol Back yang terasa rusak.
 *
 * `href`-nya SENGAJA tetap ada: klik-tengah, klik kanan, dan navigasi keyboard
 * semuanya bergantung padanya, dan menggantinya dengan `<button>` akan
 * mencabut ketiganya demi satu `preventDefault`.
 */
function jumpTo(event: MouseEvent, id: string): void {
    const target = document.getElementById(`section-${id}`)

    if (target === null) {
        // Biarkan browser yang menangani — kalau sectionnya memang tidak ada,
        // tautan yang mati lebih jujur daripada klik yang diam saja.
        return
    }

    event.preventDefault()

    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches

    target.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' })
}

const rings = computed(() =>
    props.steps.map((step) => {
        const value = Math.min(1, Math.max(0, step.progress))

        return {
            ...step,
            value,
            dash: `${value * CIRCUMFERENCE} ${CIRCUMFERENCE}`,
            percent: Math.round(value * 100),
        }
    }),
)
</script>

<template>
    <div
        class="flex w-[250px] flex-col rounded bg-surface p-4 shadow-editor ring-1 ring-cool-10"
        :aria-label="label"
    >
        <a
            v-for="(step, index) in rings"
            :key="step.id"
            :href="`#section-${step.id}`"
            class="flex w-[218px] gap-2 transition-colors hover:text-primary-90"
            @click="jumpTo($event, step.id)"
        >
            <!-- Kolom indikator: cincin, lalu garis penghubung ke langkah
                 berikutnya. Keduanya ditengahkan, seperti `layout_283e9b97`. -->
            <span class="flex flex-col items-center" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 20 20" class="shrink-0">
                    <circle
                        cx="10"
                        cy="10"
                        :r="RADIUS"
                        fill="none"
                        stroke="var(--color-cool-20)"
                        stroke-width="2"
                    />
                    <!-- Diputar −90° supaya cincinnya mulai dari atas, bukan
                         dari sisi kanan. -->
                    <circle
                        v-if="step.value > 0"
                        cx="10"
                        cy="10"
                        :r="RADIUS"
                        fill="none"
                        stroke="var(--color-primary-60)"
                        stroke-width="2"
                        stroke-linecap="round"
                        :stroke-dasharray="step.dash"
                        transform="rotate(-90 10 10)"
                    />
                </svg>

                <span
                    v-if="index < rings.length - 1"
                    class="h-[25px] w-px bg-cool-20"
                />
            </span>

            <span class="flex min-w-0 flex-col gap-1" :class="index < rings.length - 1 ? 'h-[21px]' : ''">
                <span class="truncate text-body-s text-cool-80">{{ step.label }}</span>
                <!-- Angkanya hanya dibacakan pembaca layar. Mencetaknya di
                     sebelah judul akan menggandakan informasi yang sudah
                     disampaikan cincinnya, di ruang selebar 218px. -->
                <span class="sr-only">{{ step.percent }}%</span>
            </span>
        </a>
    </div>
</template>
