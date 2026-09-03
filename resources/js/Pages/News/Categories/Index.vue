<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhCheck, PhPencilSimple, PhPlus, PhTrash, PhX } from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import IconButton from '@/Components/IconButton.vue'
import AppToggle from '@/Components/AppToggle.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'
import { FADE, RISE, SPRING_SNAP, rowDelay } from '@/motion'

/**
 * "Manage Category" — Figma `433:6116`.
 *
 * Menyunting DI TEMPAT: tiap baris berubah jadi field saat pensil ditekan, dan
 * "Add Other" menambahkan baris kosong di ujung. Tidak ada perpindahan halaman
 * untuk menambah satu kata.
 *
 * Tombol hapus MATI selama kategorinya masih dipakai. Servernya menolak juga
 * (`restrictOnDelete` di foreign key), tapi tombol yang menolak saat ditekan
 * memberi tahu terlambat — orangnya sudah memutuskan untuk menghapus.
 */
interface Row {
    id: number
    name: string
    usage: number
    isActive: boolean
}

const props = defineProps<{ categories: Row[] }>()

const { t } = useI18n()

/** Baris yang sedang disunting: id-nya, atau `'new'` untuk baris tambahan. */
const editing = ref<number | 'new' | null>(null)
const draft = ref('')
const error = ref<string | null>(null)
const saving = ref(false)
const removing = ref<Row | null>(null)

const input = ref<HTMLInputElement | null>(null)

/**
 * Ada yang akan hilang kalau halaman ini ditinggalkan sekarang.
 *
 * Baris yang sedang disunting tapi namanya belum berubah TIDAK dihitung —
 * menekan pensil lalu berpindah halaman bukan kehilangan apa pun, dan dialog
 * yang muncul untuk itu cuma mengajari orang menutupnya tanpa membaca.
 */
const hasUnsavedDraft = computed(() => {
    if (editing.value === null) return false
    if (editing.value === 'new') return draft.value.trim() !== ''

    const current = props.categories.find((c) => c.id === editing.value)

    return draft.value.trim() !== (current?.name ?? '')
})

async function focusInput(): Promise<void> {
    await nextTick()
    input.value?.focus()
    input.value?.select()
}

function startEdit(row: Row): void {
    editing.value = row.id
    draft.value = row.name
    error.value = null
    focusInput()
}

function startAdd(): void {
    editing.value = 'new'
    draft.value = ''
    error.value = null
    focusInput()
}

function cancel(): void {
    editing.value = null
    draft.value = ''
    error.value = null
}

function save(): void {
    if (draft.value.trim() === '') return

    saving.value = true
    error.value = null

    // Baris baru selalu aktif; baris yang disunting mempertahankan statusnya.
    const current = props.categories.find((c) => c.id === editing.value)
    const payload = { name: draft.value.trim(), is_active: current?.isActive ?? true }

    const options = {
        preserveScroll: true,
        onSuccess: () => cancel(),
        // Nama ganda ditangkap server; pesannya ditampilkan di baris itu
        // sendiri, bukan sebagai spanduk di atas halaman yang tidak menunjuk
        // baris mana yang bermasalah.
        onError: (errors: Record<string, string>) => (error.value = errors.name ?? null),
        onFinish: () => (saving.value = false),
    }

    if (editing.value === 'new') {
        router.post('/news/categories', payload, options)
        return
    }

    router.put(`/news/categories/${editing.value}`, payload, options)
}

/** Toggle status disimpan langsung — tidak perlu masuk mode sunting. */
function toggleStatus(row: Row, value: boolean): void {
    router.put(
        `/news/categories/${row.id}`,
        { name: row.name, is_active: value },
        { preserveScroll: true },
    )
}

function destroy(): void {
    if (!removing.value) return

    saving.value = true
    router.delete(`/news/categories/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
            removing.value = null
        },
    })
}
</script>

<template>
    <Head :title="t('news.category_title')" />

    <AdminLayout>
        <PageHeader
            :title="t('news.category_title')"
            :breadcrumbs="[
                { label: t('news.title'), href: '/news' },
                { label: t('news.list'), href: '/news' },
                { label: t('news.category_title') },
            ]"
        />

        <CardSection>
            <p class="text-body-s text-cool-100">{{ t('category.manage_hint') }}</p>

            <div class="w-full overflow-x-auto border border-cool-20">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th
                                scope="col"
                                class="bg-cool-10 px-3 py-4 text-left text-subtitle-s text-cool-100"
                            >
                                {{ t('category.column') }}
                            </th>
                            <th
                                scope="col"
                                class="bg-cool-10 px-3 py-4 text-left text-subtitle-s text-cool-100"
                            >
                                {{ t('category.usage') }}
                            </th>
                            <th
                                scope="col"
                                class="bg-cool-10 px-3 py-4 text-left text-subtitle-s text-cool-100"
                            >
                                {{ t('common.status') }}
                            </th>
                            <th scope="col" class="w-[120px] bg-cool-10 px-3 py-4">
                                <span class="sr-only">{{ t('common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <!--
                            Satu `AnimatePresence` untuk SELURUH isi tabel, bukan
                            satu per kelompok baris: baris tambahan, baris data,
                            dan keadaan kosong saling menggantikan, dan hanya
                            pengelola yang sama yang bisa menganimasikan
                            pergantian di antara ketiganya.
                        -->
                        <AnimatePresence>
                            <motion.tr
                                v-for="(row, index) in categories"
                                :key="row.id"
                                class="border-t border-cool-20"
                                :initial="{ opacity: 0, y: 8 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :exit="{ opacity: 0, x: -12, transition: FADE }"
                                :transition="{ ...RISE, delay: rowDelay(index) }"
                            >
                                <td class="px-3 py-2">
                                    <!--
                                        `mode="wait"` — field baru masuk SETELAH
                                        teksnya benar-benar hilang. Kalau
                                        keduanya menyeberang bersamaan, ada satu
                                        momen dua nama kategori terbaca bertumpuk
                                        di sel yang sama.

                                        `:initial="false"` mematikan animasi
                                        masuk saat baris pertama kali dipasang:
                                        barisnya sendiri sudah memudar masuk,
                                        dan isinya tidak perlu ikut memudar lagi
                                        di dalamnya.
                                    -->
                                    <AnimatePresence mode="wait" :initial="false">
                                        <motion.div
                                            v-if="editing === row.id"
                                            key="edit"
                                            :initial="{ opacity: 0 }"
                                            :animate="{ opacity: 1 }"
                                            :exit="{ opacity: 0 }"
                                            :transition="FADE"
                                        >
                                            <input
                                                ref="input"
                                                v-model="draft"
                                                type="text"
                                                class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90"
                                                :aria-label="t('category.name')"
                                                :aria-invalid="error ? 'true' : undefined"
                                                @keyup.enter="save"
                                                @keyup.esc="cancel"
                                            />
                                            <p
                                                v-if="error"
                                                role="alert"
                                                class="mt-1 text-body-xs text-danger"
                                            >
                                                {{ error }}
                                            </p>
                                        </motion.div>
                                        <motion.span
                                            v-else
                                            key="read"
                                            class="block text-body-s text-cool-100"
                                            :initial="{ opacity: 0 }"
                                            :animate="{ opacity: 1 }"
                                            :exit="{ opacity: 0 }"
                                            :transition="FADE"
                                        >
                                            {{ row.name }}
                                        </motion.span>
                                    </AnimatePresence>
                                </td>

                                <td class="px-3 py-2 text-body-s text-cool-100 tabular-nums">
                                    {{ row.usage }}
                                </td>

                                <td class="px-3 py-2">
                                    <AppToggle
                                        :model-value="row.isActive"
                                        :label="`${t('common.status')} ${row.name}`"
                                        :disabled="editing !== null"
                                        @update:model-value="toggleStatus(row, $event)"
                                        hide-label
                                    />
                                </td>

                                <td class="px-3 py-2">
                                    <!--
                                        Pasangan tombolnya bertukar dengan arah
                                        yang berlawanan — sunting/hapus keluar ke
                                        kanan, batal/simpan masuk dari kanan.
                                        Yang terbaca: satu set digantikan set
                                        lain, bukan empat tombol berkedip.
                                    -->
                                    <AnimatePresence mode="wait" :initial="false">
                                        <motion.div
                                            v-if="editing === row.id"
                                            key="confirm"
                                            class="flex items-center justify-end gap-2"
                                            :initial="{ opacity: 0, x: 12 }"
                                            :animate="{ opacity: 1, x: 0 }"
                                            :exit="{ opacity: 0, x: 12 }"
                                            :transition="SPRING_SNAP"
                                        >
                                            <IconButton
                                                tone="danger"
                                                :label="t('category.cancel_row')"
                                                @click="cancel"
                                            >
                                                <PhX :size="20" />
                                            </IconButton>
                                            <IconButton
                                                tone="success"
                                                :label="t('category.confirm_row')"
                                                :disabled="saving || draft.trim() === ''"
                                                @click="save"
                                            >
                                                <PhCheck :size="20" />
                                            </IconButton>
                                        </motion.div>

                                        <motion.div
                                            v-else
                                            key="idle"
                                            class="flex items-center justify-end gap-2"
                                            :initial="{ opacity: 0, x: -12 }"
                                            :animate="{ opacity: 1, x: 0 }"
                                            :exit="{ opacity: 0, x: -12 }"
                                            :transition="SPRING_SNAP"
                                        >
                                            <IconButton
                                                :label="t('category.edit_row', { name: row.name })"
                                                :disabled="editing !== null"
                                                @click="startEdit(row)"
                                            >
                                                <PhPencilSimple :size="20" />
                                            </IconButton>
                                            <IconButton
                                                tone="danger"
                                                :label="t('category.delete_row', { name: row.name })"
                                                :disabled="row.usage > 0 || editing !== null"
                                                :title="
                                                    row.usage > 0
                                                        ? t('category.cannot_delete', { count: row.usage })
                                                        : undefined
                                                "
                                                @click="removing = row"
                                            >
                                                <PhTrash :size="20" />
                                            </IconButton>
                                        </motion.div>
                                    </AnimatePresence>
                                </td>
                            </motion.tr>

                            <!-- Baris tambahan, muncul di ujung persis seperti di
                                 desain — bukan di atas, supaya urutan yang sudah
                                 dibaca orang tidak bergeser saat ia menambah. -->
                            <motion.tr
                                v-if="editing === 'new'"
                                key="new"
                                class="border-t border-cool-20"
                                :initial="{ opacity: 0, y: -8 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :exit="{ opacity: 0, y: -8, transition: FADE }"
                                :transition="SPRING_SNAP"
                            >
                                <td class="px-3 py-2">
                                    <input
                                        ref="input"
                                        v-model="draft"
                                        type="text"
                                        class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90 placeholder:text-cool-60"
                                        :placeholder="t('category.placeholder')"
                                        :aria-label="t('category.name')"
                                        :aria-invalid="error ? 'true' : undefined"
                                        @keyup.enter="save"
                                        @keyup.esc="cancel"
                                    />
                                    <p v-if="error" role="alert" class="mt-1 text-body-xs text-danger">
                                        {{ error }}
                                    </p>
                                </td>

                                <td class="px-3 py-2" />
                                <td class="px-3 py-2" />

                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <IconButton
                                            tone="danger"
                                            :label="t('category.cancel_row')"
                                            @click="cancel"
                                        >
                                            <PhX :size="20" />
                                        </IconButton>
                                        <IconButton
                                            tone="success"
                                            :label="t('category.confirm_row')"
                                            :disabled="saving || draft.trim() === ''"
                                            @click="save"
                                        >
                                            <PhCheck :size="20" />
                                        </IconButton>
                                    </div>
                                </td>
                            </motion.tr>

                            <motion.tr
                                v-if="categories.length === 0 && editing !== 'new'"
                                key="empty"
                                :initial="{ opacity: 0 }"
                                :animate="{ opacity: 1 }"
                                :exit="{ opacity: 0 }"
                                :transition="FADE"
                            >
                                <td colspan="4" class="border-t border-cool-20 px-3 py-8 text-center text-body-s text-cool-60">
                                    {{ t('category.empty') }}
                                </td>
                            </motion.tr>
                        </AnimatePresence>
                    </tbody>
                </table>
            </div>

            <motion.button
                type="button"
                class="flex h-10 w-fit cursor-pointer items-center gap-2 border-2 border-dashed border-cool-30 bg-surface px-3 text-button-s text-cool-100 transition-colors hover:border-cool-60 disabled:cursor-not-allowed disabled:text-cool-40"
                :disabled="editing !== null"
                :while-hover="editing !== null ? undefined : { scale: 1.02 }"
                :while-press="editing !== null ? undefined : { scale: 0.97 }"
                :transition="SPRING_SNAP"
                @click="startAdd"
            >
                <PhPlus :size="24" aria-hidden="true" />
                {{ t('common.add_other') }}
            </motion.button>
        </CardSection>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('category.delete_title')"
            :description="t('category.delete_body', { name: removing?.name ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="saving"
            @confirm="destroy"
            @cancel="removing = null"
        />

        <UnsavedGuard :dirty="hasUnsavedDraft" />
    </AdminLayout>
</template>
