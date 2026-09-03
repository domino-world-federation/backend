/**
 * Bentuk props yang dibagi `HandleInertiaRequests::share()`.
 *
 * Ditulis tangan, bukan digenerate: satu-satunya sumbernya ada di PHP, dan
 * file ini harus ikut berubah saat `share()` berubah — kalau lupa, `vue-tsc`
 * belum tentu menangkapnya, jadi keduanya disebut saling menunjuk di komentar.
 */

export type NavIconName = string

export interface NavChild {
    label: string
    key: string
    href: string
    built: boolean
}

export interface NavNode {
    type: 'item' | 'group' | 'heading'
    label: string
    icon?: NavIconName
    key?: string
    href?: string
    built?: boolean
    children?: NavChild[]
}

export interface AuthUser {
    name: string
    email: string
    avatarUrl: string | null
}

export interface LocaleOption {
    value: string
    label: string
    short: string
}

export interface SharedProps {
    auth: { user: AuthUser | null }
    navigation: NavNode[]
    flash: { success: string | null; error: string | null }
    locale: string
    /** Pengalih bahasa di topbar; mati secara bawaan. */
    localeSwitchable: boolean
    locales: LocaleOption[]
    /** Kamus bersarang dari `lang/{locale}/backoffice.php`. */
    translations: Record<string, unknown>
    [key: string]: unknown
}

/** Satu kolom `DataTable`. `width` ditulis apa adanya ke `style`, mis. `'40px'`. */
export interface TableColumn {
    key: string
    label: string
    align?: 'left' | 'right'
    width?: string
}

/** Satu ruas breadcrumb. Ruas terakhir tidak pernah jadi tautan. */
export interface Crumb {
    label: string
    href?: string
}

// --- Dashboard -------------------------------------------------------------

/** Kartu statistik: label · nilai · selisih · sparkline 12 titik. */
export interface StatTileData {
    key: string
    label: string
    value: number
    /** Persen, bertanda. */
    delta: number
    deltaLabel: string
    /** Apakah naik berarti membaik. "Draf" dan "Pesan belum dibaca" -> false. */
    upIsGood: boolean
    spark: number[]
}

/** Satu posisi X pada grafik garis; `values` sejajar dengan urutan `series`. */
export interface SeriesPoint {
    label: string
    iso: string
    values: number[]
}

export interface SeriesMeta {
    key: string
    label: string
}

export interface ColumnPoint {
    label: string
    iso: string
    value: number
}

export type SectionStatus = 'ready' | 'incomplete' | 'empty' | 'unknown'

export interface LandingSection {
    key: string
    label: string
    status: SectionStatus
    note: string
    href: string
}

export interface ActivityEntry {
    /** Prefiks + id, mis. "news-12" — baris berasal dari beberapa tabel. */
    id: string
    actor: string
    action: string
    target: string
    /** ISO 8601. */
    at: string
    href: string
}

export interface FeaturedEvent {
    name: string
    location: string
    startsAt: string
    href: string
}

/** Bentuk paginator Laravel yang dikirim ke Inertia. */
export interface Paginated<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
}
