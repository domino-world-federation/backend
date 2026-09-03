import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { SharedProps } from '@/types'

/**
 * Terjemahan di sisi Vue.
 *
 * Kamusnya datang dari Laravel lewat props Inertia — TIDAK ada berkas
 * terjemahan kedua di sisi JS. Satu sumber berarti pesan validasi (yang selalu
 * datang dari server) dan label tombol tidak akan pernah berbeda bahasa di
 * layar yang sama.
 */
type Replacements = Record<string, string | number>

/** `dashboard.stat_published` -> menyusuri objek bersarang. */
function lookup(dict: unknown, key: string): string | undefined {
    const value = key.split('.').reduce<unknown>(
        (node, part) =>
            node !== null && typeof node === 'object' ? (node as Record<string, unknown>)[part] : undefined,
        dict,
    )

    return typeof value === 'string' ? value : undefined
}

export function useI18n() {
    const page = usePage<SharedProps>()

    const locale = computed(() => page.props.locale)
    const locales = computed(() => page.props.locales)
    const localeSwitchable = computed(() => page.props.localeSwitchable)

    /**
     * Kunci yang tidak ditemukan dikembalikan APA ADANYA, bukan jadi string
     * kosong. Layar yang menampilkan `news.field_title` jelek tapi jelas rusak;
     * layar dengan tombol tanpa tulisan terlihat baik-baik saja dan diam-diam
     * tidak bisa dipakai.
     */
    function t(key: string, replace: Replacements = {}): string {
        const line = lookup(page.props.translations, key) ?? key

        return Object.entries(replace).reduce(
            (text, [token, value]) => text.replaceAll(`:${token}`, String(value)),
            line,
        )
    }

    return { t, locale, locales, localeSwitchable }
}
