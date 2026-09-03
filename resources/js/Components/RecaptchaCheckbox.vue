<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Kotak centang reCAPTCHA v2 ("I'm not a robot").
 *
 * Dirender EKSPLISIT (`render=explicit`), bukan lewat `class="g-recaptcha"`.
 * Alasannya Inertia: rendering otomatis hanya memindai DOM sekali saat skrip
 * dimuat, sementara halaman login di sini bisa dipasang ulang tanpa memuat
 * ulang dokumen — misalnya setelah logout. Dengan cara otomatis, kunjungan
 * kedua menghasilkan kotak kosong tanpa pesan galat apa pun.
 *
 * Skripnya dimuat di sini, bukan di `app.blade.php`: halaman lain tidak perlu
 * menyeret skrip pihak ketiga, dan kalau captcha dimatikan komponen ini tidak
 * dipasang sama sekali sehingga tidak ada satu pun request ke Google.
 */
const props = defineProps<{ siteKey: string; error?: string }>()

const token = defineModel<string>({ default: '' })

const host = ref<HTMLElement | null>(null)
const widgetId = ref<number | null>(null)
const failed = ref(false)

const SCRIPT_ID = 'recaptcha-api'
const CALLBACK = '__dwfRecaptchaReady'

declare global {
    interface Window {
        grecaptcha?: {
            render: (el: HTMLElement, options: Record<string, unknown>) => number
            reset: (id?: number) => void
        }
        [CALLBACK]?: () => void
    }
}

/** Memuat skrip sekali per dokumen dan menunggu Google siap. */
function loadScript(): Promise<void> {
    if (window.grecaptcha?.render) return Promise.resolve()

    return new Promise((resolve, reject) => {
        const existing = document.getElementById(SCRIPT_ID)

        // Skrip bisa sudah ada tapi belum selesai memuat — kunjungan kedua
        // dalam sesi Inertia yang sama. Menumpuk `onload` lebih benar daripada
        // menyisipkan tag kedua.
        const previous = window[CALLBACK]
        window[CALLBACK] = () => {
            previous?.()
            resolve()
        }

        if (existing) return

        const script = document.createElement('script')
        script.id = SCRIPT_ID
        script.src = `https://www.google.com/recaptcha/api.js?onload=${CALLBACK}&render=explicit`
        script.async = true
        script.defer = true
        script.onerror = () => reject(new Error('gagal memuat reCAPTCHA'))
        document.head.appendChild(script)
    })
}

onMounted(async () => {
    try {
        await loadScript()
    } catch {
        // Skrip Google diblokir (pemblokir iklan, jaringan tertutup). Server
        // tetap menolak login tanpa token, jadi yang bisa dilakukan di sini
        // adalah mengatakannya — bukan diam dan menyisakan kotak kosong.
        failed.value = true
        return
    }

    if (!host.value || !window.grecaptcha) return

    widgetId.value = window.grecaptcha.render(host.value, {
        sitekey: props.siteKey,
        callback: (value: string) => (token.value = value),
        // Token kedaluwarsa setelah dua menit. Tanpa handler ini, orang yang
        // mengetik sandinya perlahan menekan Log In dengan token mati dan
        // hanya melihat "verifikasi gagal" tanpa tahu sebabnya.
        'expired-callback': () => (token.value = ''),
        'error-callback': () => (token.value = ''),
    })
})

onBeforeUnmount(() => {
    token.value = ''
})

/** Dipanggil halaman login setelah percobaan yang gagal. */
function reset(): void {
    token.value = ''
    if (widgetId.value !== null) window.grecaptcha?.reset(widgetId.value)
}

defineExpose({ reset })
</script>

<template>
    <div class="flex flex-col gap-2">
        <div ref="host" />

        <p v-if="failed" role="alert" class="text-body-xs text-danger">
            reCAPTCHA tidak bisa dimuat. Matikan pemblokir iklan atau periksa
            koneksi, lalu muat ulang halaman ini.
        </p>

        <p v-else-if="error" role="alert" class="text-body-xs text-danger">{{ error }}</p>
    </div>
</template>
