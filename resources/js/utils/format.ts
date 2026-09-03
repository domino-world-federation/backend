/**
 * Pemformatan tanggal untuk seluruh backoffice.
 *
 * Zona waktunya dipatok ke Asia/Jakarta, bukan zona browser: Figma menulis
 * "19/08/2026 03:21 WIB" (`252:4480`), dan seorang admin di zona lain yang
 * membuka daftar yang sama harus melihat jam yang sama dengan rekannya —
 * kalau tidak, "jam berapa berita ini tayang?" punya dua jawaban.
 */
const TZ = 'Asia/Jakarta'

export function formatDateTime(iso: string | null | undefined): string {
    if (!iso) return '—'

    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return '—'

    const parts = new Intl.DateTimeFormat('id-ID', {
        timeZone: TZ,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(date)

    const get = (type: string) => parts.find((p) => p.type === type)?.value ?? ''

    return `${get('day')}/${get('month')}/${get('year')} ${get('hour')}:${get('minute')} WIB`
}

export function formatDate(iso: string | null | undefined): string {
    if (!iso) return '—'

    const date = new Date(iso)
    if (Number.isNaN(date.getTime())) return '—'

    return new Intl.DateTimeFormat('id-ID', {
        timeZone: TZ,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date)
}
