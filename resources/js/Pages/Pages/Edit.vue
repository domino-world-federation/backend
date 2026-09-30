<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import {
    PhArrowSquareOut,
    PhArrowCounterClockwise,
    PhDesktop,
    PhDeviceMobile,
    PhDeviceTablet,
} from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppField from '@/Components/AppField.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import ContextNote from '@/Components/ContextNote.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Editor halaman — pratinjau halaman asli di kiri, panel field di kanan.
 *
 * **Pratinjaunya situs publik itu sendiri**, dalam iframe, dibuka dengan token
 * pratinjau sehingga membaca draf. Layar ini tidak menggambar ulang apa pun:
 * yang tampil persis yang akan tayang. Dua arah percakapan lewat `postMessage`,
 * dan setiap pesan dari iframe diperiksa origin-nya (`siteOrigin`):
 *
 * - iframe → sini: `dwf-cms:ready` (beserta naskah bawaan tiap field, dipakai
 *   sebagai placeholder panel), `dwf-cms:select` (orang mengklik teks), dan
 *   `dwf-cms:navigation-blocked` (orang mengklik tautan ke halaman lain —
 *   pratinjau menolaknya, dan layar ini mengatakan kenapa);
 * - sini → iframe: `dwf-cms:values` (semua nilai panel, setiap ketikan) dan
 *   `dwf-cms:highlight` (field di panel difokuskan).
 *
 * **Lebar pratinjau disimulasikan, bukan diwarisi.** Kolom pratinjau di layar
 * ini sekitar 700px; iframe selebar itu akan merender tata letak TABLET situs
 * bahkan dalam mode "Desktop". Iframe diberi lebar mode yang dipilih (1440 /
 * 820 / 390) lalu diperkecil dengan `transform: scale()` agar muat.
 *
 * Kosong = naskah bawaan kode. Simpan Draf tidak mengubah situs publik;
 * Terbitkan menyimpan yang ada di layar lalu menerbitkan semua draf.
 */
interface Field {
    key: string
    section: string
    label: string
    type: 'text' | 'textarea' | 'lines' | 'url'
    max: number
    lines?: [number, number]
}

interface Section {
    key: string
    label: string
    fields: Field[]
    elsewhere: Array<{ label: string; href: string }>
}

const props = defineProps<{
    page: { key: string; label: string; path: string }
    sections: Section[]
    published: Record<string, string>
    drafts: Record<string, string>
    previewUrl: string
    siteOrigin: string
}>()

const { t } = useI18n()

// ------------------------------------------------------------------ nilai

function initialValues(): Record<string, string> {
    const out: Record<string, string> = {}
    for (const section of props.sections) {
        for (const field of section.fields) {
            out[field.key] = props.drafts[field.key] ?? props.published[field.key] ?? ''
        }
    }
    return out
}

const form = useForm({ values: initialValues() })

/** Naskah bawaan situs per field, dikirim iframe saat siap. */
const defaults = ref<Record<string, string>>({})

const hasDrafts = computed(() => Object.keys(props.drafts).length > 0)

function isDraft(key: string): boolean {
    return key in props.drafts
}

function lengthOf(field: Field): number {
    const value = form.values[field.key] ?? ''
    if (field.type !== 'lines') return value.length
    return Math.max(0, ...value.split('\n').map((line) => line.trim().length))
}

function reset(key: string): void {
    form.values[key] = ''
}

// ------------------------------------------------------------------ simpan

function afterSave(): void {
    form.defaults({ values: { ...form.values } })
    form.reset()
}

function saveDraft(): void {
    form.put(`/pages/${props.page.key}/draft`, { preserveScroll: true, onSuccess: afterSave })
}

function publish(): void {
    form.post(`/pages/${props.page.key}/publish`, { preserveScroll: true, onSuccess: afterSave })
}

const confirmingDiscard = ref(false)
const discarding = ref(false)

function discard(): void {
    discarding.value = true
    router.delete(`/pages/${props.page.key}/draft`, {
        preserveScroll: true,
        onFinish: () => {
            discarding.value = false
            confirmingDiscard.value = false
        },
        onSuccess: () => {
            form.defaults({ values: initialValues() })
            form.reset()
        },
    })
}

// ------------------------------------------------------------- pratinjau

type Mode = 'desktop' | 'tablet' | 'mobile'
const WIDTHS: Record<Mode, number> = { desktop: 1440, tablet: 820, mobile: 390 }
const mode = ref<Mode>('desktop')

const frame = ref<HTMLIFrameElement | null>(null)
const stage = ref<HTMLDivElement | null>(null)
const stageSize = ref({ width: 0, height: 0 })
const ready = ref(false)
const offline = ref(false)

const scale = computed(() =>
    stageSize.value.width ? Math.min(1, stageSize.value.width / WIDTHS[mode.value]) : 1,
)

const frameStyle = computed(() => ({
    width: `${WIDTHS[mode.value]}px`,
    height: `${stageSize.value.height / scale.value}px`,
    transform: `scale(${scale.value})`,
}))

function post(message: Record<string, unknown>): void {
    frame.value?.contentWindow?.postMessage(message, props.siteOrigin)
}

/** Nilai panel dalam bentuk yang dibaca situs: `lines` jadi larik, kosong = bawaan. */
function outgoing(): Record<string, string | string[] | null> {
    const lineKeys = new Set(
        props.sections.flatMap((s) => s.fields.filter((f) => f.type === 'lines').map((f) => f.key)),
    )
    const out: Record<string, string | string[] | null> = {}
    for (const [key, raw] of Object.entries(form.values)) {
        const value = (raw ?? '').trim()
        if (value === '') {
            out[key] = null
        } else if (lineKeys.has(key)) {
            out[key] = value.split('\n').map((l) => l.trim()).filter(Boolean)
        } else {
            out[key] = value
        }
    }
    return out
}

watch(
    () => form.values,
    () => ready.value && post({ type: 'dwf-cms:values', values: outgoing() }),
    { deep: true },
)

const activeKey = ref<string | null>(null)

/** Pesan singkat saat pratinjau menolak pindah halaman. */
const blockedNotice = ref(false)
let blockedTimer: ReturnType<typeof setTimeout> | undefined

function showBlocked(): void {
    blockedNotice.value = true
    clearTimeout(blockedTimer)
    blockedTimer = setTimeout(() => (blockedNotice.value = false), 3500)
}

function fieldId(key: string): string {
    return `field-${key.replaceAll('.', '-')}`
}

async function select(key: string): Promise<void> {
    activeKey.value = key
    await nextTick()
    const el = document.getElementById(fieldId(key))
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    el?.focus({ preventScroll: true })
}

function onFocus(key: string): void {
    activeKey.value = key
    post({ type: 'dwf-cms:highlight', key })
}

function onMessage(event: MessageEvent): void {
    if (event.origin !== props.siteOrigin) return
    const data = event.data as { type?: string; key?: string; defaults?: Record<string, string | string[]> }

    if (data?.type === 'dwf-cms:ready') {
        ready.value = true
        offline.value = false
        defaults.value = Object.fromEntries(
            Object.entries(data.defaults ?? {}).map(([k, v]) => [k, Array.isArray(v) ? v.join('\n') : v]),
        )
        post({ type: 'dwf-cms:values', values: outgoing() })
    } else if (data?.type === 'dwf-cms:select' && data.key) {
        void select(data.key)
    } else if (data?.type === 'dwf-cms:navigation-blocked') {
        showBlocked()
    }
}

let observer: ResizeObserver | undefined
let offlineTimer: ReturnType<typeof setTimeout> | undefined

onMounted(() => {
    window.addEventListener('message', onMessage)
    observer = new ResizeObserver(([entry]) => {
        stageSize.value = { width: entry.contentRect.width, height: entry.contentRect.height }
    })
    if (stage.value) observer.observe(stage.value)
    offlineTimer = setTimeout(() => (offline.value = !ready.value), 12_000)
})

onBeforeUnmount(() => {
    window.removeEventListener('message', onMessage)
    observer?.disconnect()
    clearTimeout(offlineTimer)
    clearTimeout(blockedTimer)
})

const liveUrl = computed(() => props.siteOrigin + props.page.path)
</script>

<template>
    <Head :title="`${t('pages.title')} — ${page.label}`" />

    <AdminLayout>
        <PageHeader
            :title="page.label"
            :breadcrumbs="[{ label: t('pages.title'), href: '/pages' }, { label: page.label }]"
        >
            <template #description>
                <span class="font-mono">{{ page.path }}</span>
                <span v-if="form.isDirty" class="ml-3 text-status-warning">● {{ t('pages.unsaved') }}</span>
            </template>

            <template #actions>
                <AppButton :href="liveUrl" external variant="link" size="s">
                    {{ t('pages.open_site') }}
                    <template #iconRight><PhArrowSquareOut :size="16" /></template>
                </AppButton>
                <AppButton
                    v-if="hasDrafts"
                    variant="outline"
                    size="s"
                    :disabled="form.processing"
                    @click="confirmingDiscard = true"
                >
                    {{ t('pages.discard') }}
                </AppButton>
                <AppButton variant="outline" size="s" :disabled="form.processing" @click="saveDraft">
                    {{ t('pages.save_draft') }}
                </AppButton>
                <AppButton size="s" :disabled="form.processing" @click="publish">
                    {{ t('pages.publish') }}
                </AppButton>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
            <!-- Pratinjau -->
            <section class="flex min-w-0 flex-col gap-3">
                <div class="flex items-center justify-between gap-4">
                    <div class="inline-flex border border-cool-20 bg-surface p-1">
                        <button
                            v-for="m in (['desktop', 'tablet', 'mobile'] as Mode[])"
                            :key="m"
                            type="button"
                            :aria-pressed="mode === m"
                            :class="[
                                'flex items-center gap-2 px-3 py-1.5 text-body-xs',
                                mode === m ? 'bg-cool-10 text-cool-100' : 'text-cool-60 hover:text-cool-90',
                            ]"
                            @click="mode = m"
                        >
                            <PhDesktop v-if="m === 'desktop'" :size="16" />
                            <PhDeviceTablet v-else-if="m === 'tablet'" :size="16" />
                            <PhDeviceMobile v-else :size="16" />
                            {{ t(`pages.${m}`) }}
                        </button>
                    </div>
                    <span class="text-body-xs text-cool-60">{{ t('pages.click_hint') }}</span>
                </div>

                <div
                    ref="stage"
                    class="relative h-[calc(100vh-240px)] min-h-[480px] overflow-hidden border border-cool-20 bg-cool-10"
                >
                    <iframe
                        ref="frame"
                        :src="previewUrl"
                        :title="page.label"
                        class="absolute top-0 left-1/2 origin-top -translate-x-1/2 border-0 bg-white"
                        :style="frameStyle"
                    />
                    <p
                        v-if="blockedNotice"
                        role="status"
                        class="absolute inset-x-4 top-4 border border-cool-20 bg-surface px-4 py-3 text-body-s text-cool-90 shadow-lg"
                    >
                        {{ t('pages.navigation_blocked') }}
                    </p>
                    <p
                        v-if="!ready"
                        class="absolute inset-x-0 bottom-0 bg-surface/90 px-4 py-3 text-body-xs text-cool-70"
                    >
                        {{ offline ? t('pages.preview_offline', { url: siteOrigin }) : t('pages.preview_loading') }}
                    </p>
                </div>
            </section>

            <!-- Panel -->
            <aside class="flex max-h-[calc(100vh-190px)] flex-col gap-6 overflow-y-auto pr-1 xl:sticky xl:top-6">
                <ContextNote>{{ t('pages.default_hint') }}</ContextNote>

                <section
                    v-for="section in sections"
                    :key="section.key"
                    class="flex flex-col gap-4 border border-cool-20 bg-surface p-4"
                >
                    <h2 class="text-heading-6 text-cool-100">{{ section.label }}</h2>

                    <div
                        v-for="field in section.fields"
                        :key="field.key"
                        :class="[
                            'flex flex-col gap-1.5 border-l-2 pl-3 transition-colors',
                            activeKey === field.key ? 'border-primary-60' : 'border-transparent',
                        ]"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <label :for="fieldId(field.key)" class="text-body-xs font-medium text-cool-90">
                                {{ field.label }}
                                <span v-if="isDraft(field.key)" class="ml-1 text-status-warning">
                                    · {{ t('pages.has_draft') }}
                                </span>
                            </label>
                            <span
                                :class="[
                                    'shrink-0 text-body-xs tabular-nums',
                                    lengthOf(field) > field.max ? 'text-danger' : 'text-cool-60',
                                ]"
                            >
                                {{ t('pages.chars', { count: lengthOf(field), max: field.max }) }}
                            </span>
                        </div>

                        <AppField
                            :id="fieldId(field.key)"
                            v-model="form.values[field.key]"
                            :textarea="field.type === 'textarea' || field.type === 'lines'"
                            :class="field.type === 'url' ? 'font-mono' : undefined"
                            :placeholder="defaults[field.key] ?? ''"
                            :error="(form.errors as Record<string, string>)[`values.${field.key}`]"
                            @focus="onFocus(field.key)"
                        />

                        <div class="flex items-center justify-between gap-3">
                            <span v-if="field.type === 'lines'" class="text-body-xs text-cool-60">
                                {{ t('pages.lines_hint') }}
                            </span>
                            <span v-else-if="field.type === 'url'" class="text-body-xs text-cool-60">
                                {{ t('pages.url_hint') }}
                            </span>
                            <span v-else />
                            <button
                                v-if="form.values[field.key]"
                                type="button"
                                class="inline-flex items-center gap-1 text-body-xs text-cool-60 hover:text-cool-90"
                                @click="reset(field.key)"
                            >
                                <PhArrowCounterClockwise :size="12" />
                                {{ t('pages.reset') }}
                            </button>
                        </div>
                    </div>

                    <div v-if="section.elsewhere.length" class="flex flex-col gap-2 border-t border-cool-20 pt-3">
                        <span class="text-body-xs font-medium text-cool-60">{{ t('pages.elsewhere') }}</span>
                        <div
                            v-for="link in section.elsewhere"
                            :key="link.href"
                            class="flex items-center justify-between gap-3"
                        >
                            <span class="text-body-s text-cool-90">{{ link.label }}</span>
                            <AppButton :href="link.href" variant="outline" size="s">
                                {{ t('pages.open') }}
                                <template #iconRight><PhArrowSquareOut :size="16" /></template>
                            </AppButton>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <ConfirmDialog
            :open="confirmingDiscard"
            variant="deletion"
            :title="t('pages.discard_title')"
            :description="t('pages.discard_body')"
            :confirm-label="t('pages.discard')"
            :cancel-label="t('common.cancel')"
            :processing="discarding"
            @confirm="discard"
            @cancel="confirmingDiscard = false"
        />

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
