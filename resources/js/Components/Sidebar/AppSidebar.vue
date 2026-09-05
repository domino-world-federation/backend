<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { PhArrowSquareOut, PhCaretDown, PhTextAlignLeft } from '@phosphor-icons/vue'
import NavIcon from '@/Components/Sidebar/NavIcon.vue'
import { useI18n } from '@/composables/useI18n'
import type { NavNode, SharedProps } from '@/types'

/**
 * Sidebar backoffice — komponen Figma `252:3403` ("Landing Page Sidebar").
 *
 * Lebar 312, latar #101010 dengan overlay gradient dari y=80 ke bawah
 * (`252:3402`), item aktif utama jadi pill terang #F5F5F5, sub-item aktif
 * #383838 dengan padding kiri 48.
 *
 * Strukturnya tidak ditulis di sini — datang dari `App\Support\Navigation`
 * lewat props bersama Inertia. Lihat komentar di file PHP itu.
 */
const props = defineProps<{ collapsed: boolean }>()
const emit = defineEmits<{ 'update:collapsed': [value: boolean] }>()

const page = usePage<SharedProps>()
const { t } = useI18n()

const navigation = computed(() => page.props.navigation)
const user = computed(() => page.props.auth.user)

const currentPath = computed(() => new URL(page.url, 'http://localhost').pathname)

/** Semua `href` di pohon navigasi, datar. */
const hrefs = computed<string[]>(() => {
    const out: string[] = []

    for (const node of navigation.value) {
        if (node.href) out.push(node.href)
        for (const child of node.children ?? []) {
            if (child.href) out.push(child.href)
        }
    }

    return out
})

/**
 * Menu yang menyala: `href` TERPANJANG yang memuat halaman sekarang.
 *
 * Sebelumnya perbandingannya persis (`===`), dan itu berarti tiap layar anak
 * memadamkan menunya sendiri — buka `/news`, menu "News Articles" menyala; tekan
 * "Add News" dan di `/news/create` sidebar-nya kosong, seolah halaman itu tidak
 * berada di mana-mana.
 *
 * Yang terpanjang, bukan yang pertama cocok: kalau suatu saat ada menu `/users`
 * dan `/users/roles` sekaligus, awalan `/users` juga cocok untuk keduanya dan
 * yang menyala harus tetap satu — yang paling dekat dengan halamannya.
 */
const activeHref = computed<string | null>(() => {
    const path = currentPath.value
    let best: string | null = null

    for (const href of hrefs.value) {
        if (path !== href && !path.startsWith(`${href}/`)) continue
        if (best === null || href.length > best.length) best = href
    }

    return best
})

function isActive(href: string | undefined): boolean {
    return href !== undefined && href === activeHref.value
}

/** Grup terbuka kalau salah satu anaknya adalah halaman yang sedang dibuka. */
function groupHasActiveChild(node: NavNode): boolean {
    return node.children?.some((child) => isActive(child.href)) ?? false
}

const manuallyToggled = ref<Record<string, boolean>>({})

function isExpanded(node: NavNode): boolean {
    const key = node.key ?? node.label
    return manuallyToggled.value[key] ?? groupHasActiveChild(node)
}

function toggleGroup(node: NavNode): void {
    const key = node.key ?? node.label
    manuallyToggled.value[key] = !isExpanded(node)
}

const initials = computed(() =>
    (user.value?.name ?? '')
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0] ?? '')
        .join('')
        .toUpperCase(),
)

function logout(): void {
    router.post('/logout')
}

// `relative` supaya badge bisa duduk di pojok ikon saat sidebar menciut —
// tanpa itu ia dipatok ke leluhur berposisi terdekat, yang jauh di atas sana.
const mainItemClass =
    'relative flex w-full items-center gap-3 rounded px-3 py-1.5 text-nav-l transition-colors'
</script>

<template>
    <!--
        `sticky top-0 h-screen`: sidebar setinggi viewport dan diam saat halaman
        digulir. Sebelumnya ia ikut tumbuh mengikuti tinggi halaman, jadi di
        halaman panjang (Design System, News List) menu-nya tergulir keluar layar
        dan yang tersisa cuma latar hitam.

        Yang menggulir sekarang cuma daftar menunya sendiri; header logo dan blok
        akun dipatok di ujung atas dan bawah.

        Latarnya #101010 RATA, tanpa overlay gradient. Figma `252:3402` menggambar
        gradient ke #636363 di bagian bawah, tapi diminta dibuang: dengan sidebar
        setinggi viewport, ujung terang gradient itu jatuh di bawah menu terakhir
        dan terbaca seperti panel kedua yang menempel, bukan seperti satu bidang.
    -->
    <aside
        class="sticky top-0 flex h-screen shrink-0 flex-col gap-3 overflow-hidden bg-shell px-3 py-4 transition-[width] duration-200"
        :class="collapsed ? 'w-[76px]' : 'w-[312px]'"
    >
        <div class="relative z-10 flex shrink-0 flex-col gap-4">
            <div
                class="flex gap-2"
                :class="collapsed ? 'flex-col items-center' : 'items-center justify-between'"
            >
                <Link href="/dashboard" class="flex items-center gap-2 text-nav-l-semibold text-white">
                    <!-- Logo tetap terlihat saat sidebar menyempit — yang
                         disembunyikan hanya teksnya. Tanpa `alt` kosong,
                         pembaca layar membacakan mereknya dua kali: sekali
                         dari gambar, sekali dari teks di sebelahnya. -->
                    <img
                        src="/assets/images/navbar-logo.svg"
                        alt=""
                        width="37"
                        height="40"
                        class="h-10 w-[37px] shrink-0"
                    />
                    <span v-show="!collapsed">DWF Backoffice</span>
                </Link>

                <button
                    type="button"
                    class="cursor-pointer p-1.5 text-white"
                    :aria-label="collapsed ? t('nav.toggle_sidebar_open') : t('nav.toggle_sidebar_close')"
                    :aria-expanded="!collapsed"
                    @click="emit('update:collapsed', !props.collapsed)"
                >
                    <PhTextAlignLeft :size="20" aria-hidden="true" />
                </button>
            </div>
        </div>

        <!-- Satu-satunya bagian yang menggulir. `min-h-0` wajib: tanpa itu item
             flex menolak menyusut di bawah tinggi isinya, dan `overflow-y-auto`
             tidak pernah kebagian ruang untuk menggulir. -->
        <nav
            class="sidebar-scroll relative z-10 flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto"
            :aria-label="t('nav.main')"
        >
                <template v-for="(node, index) in navigation" :key="`${node.label}-${index}`">
                    <p
                        v-if="node.type === 'heading'"
                        v-show="!collapsed"
                        class="px-2 pt-3 pb-1 text-nav-m-semibold text-shell-muted"
                    >
                        {{ node.label }}
                    </p>

                    <Link
                        v-else-if="node.type === 'item'"
                        :href="node.href!"
                        :class="[
                            mainItemClass,
                            isActive(node.href)
                                ? 'bg-shell-main-active text-shell'
                                : 'text-white hover:bg-white/10',
                        ]"
                        :aria-current="isActive(node.href) ? 'page' : undefined"
                        :title="collapsed ? node.label : undefined"
                    >
                        <NavIcon :name="node.icon" />
                        <span v-show="!collapsed" class="flex-1 truncate">{{ node.label }}</span>

                        <!-- Yang belum dikerjakan di modul itu. Angkanya
                             DICETAK, bukan titik: "ada sesuatu" dan "ada dua
                             puluh tiga" menuntut hari yang berbeda.

                             Saat sidebar menciut, angkanya tetap ada tapi jadi
                             titik di pojok ikon — label yang ikut hilang membuat
                             angka telanjang tidak bisa dibaca milik siapa, dan
                             menyembunyikannya sama sekali berarti menciutkan
                             sidebar diam-diam menyembunyikan pekerjaan. -->
                        <span
                            v-if="node.badge"
                            :class="[
                                'shrink-0 rounded-full bg-danger font-semibold text-white',
                                collapsed
                                    ? 'absolute top-1 right-1 h-2 w-2'
                                    : 'px-1.5 py-0.5 text-[10px] leading-none',
                            ]"
                            :aria-label="`${node.badge}`"
                        >
                            <template v-if="!collapsed">{{ node.badge }}</template>
                        </span>
                    </Link>

                    <div v-else class="flex flex-col">
                        <button
                            type="button"
                            :class="[
                                mainItemClass,
                                'cursor-pointer',
                                groupHasActiveChild(node)
                                    ? 'bg-shell-main-active text-shell'
                                    : 'text-white hover:bg-white/10',
                            ]"
                            :aria-expanded="isExpanded(node)"
                            :title="collapsed ? node.label : undefined"
                            @click="toggleGroup(node)"
                        >
                            <NavIcon :name="node.icon" />
                            <span v-show="!collapsed" class="flex-1 truncate text-left">
                                {{ node.label }}
                            </span>
                            <PhCaretDown
                                v-show="!collapsed"
                                :size="20"
                                class="shrink-0 transition-transform"
                                :class="isExpanded(node) ? 'rotate-180' : ''"
                                aria-hidden="true"
                            />
                        </button>

                        <div v-show="isExpanded(node) && !collapsed" class="flex flex-col">
                            <Link
                                v-for="child in node.children"
                                :key="child.key"
                                :href="child.href"
                                class="flex items-center gap-3 rounded py-1.5 pr-3 pl-11 text-nav-l text-white transition-colors"
                                :class="
                                    isActive(child.href)
                                        ? 'bg-shell-sub-active'
                                        : 'hover:bg-white/10'
                                "
                                :aria-current="isActive(child.href) ? 'page' : undefined"
                            >
                                <span class="truncate">{{ child.label }}</span>
                            </Link>
                        </div>
                    </div>
                </template>
        </nav>

        <!-- Blok akun `252:3263`, dipatok di dasar sidebar. -->
        <div
            v-if="user"
            class="relative z-10 flex shrink-0 items-center justify-between border-t border-white/10 px-3 pt-3"
        >
            <div class="flex min-w-0 items-center gap-2">
                <img
                    v-if="user.avatarUrl"
                    :src="user.avatarUrl"
                    alt=""
                    class="size-10 shrink-0 rounded-full object-cover"
                />
                <span
                    v-else
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-white/15 text-nav-m-semibold text-white"
                    aria-hidden="true"
                >
                    {{ initials }}
                </span>

                <span v-show="!collapsed" class="flex min-w-0 flex-col">
                    <span class="truncate text-nav-m text-white">{{ user.name }}</span>
                    <span class="truncate text-nav-m text-shell-muted">{{ user.email }}</span>
                </span>
            </div>

            <button
                v-show="!collapsed"
                type="button"
                class="shrink-0 cursor-pointer text-white"
                :aria-label="t('nav.sign_out')"
                @click="logout"
            >
                <PhArrowSquareOut :size="20" aria-hidden="true" />
            </button>
        </div>
    </aside>
</template>
