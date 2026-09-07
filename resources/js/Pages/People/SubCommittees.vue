<script setup lang="ts">
import { computed, nextTick, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import {
    PhArrowDown,
    PhArrowUp,
    PhCheck,
    PhPencilSimple,
    PhPlus,
    PhTrash,
    PhX,
} from '@phosphor-icons/vue'
import { AnimatePresence, motion } from 'motion-v'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import IconButton from '@/Components/IconButton.vue'
import AppToggle from '@/Components/AppToggle.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import ContextNote from '@/Components/ContextNote.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import { useI18n } from '@/composables/useI18n'
import { FADE, RISE, SPRING_SNAP, rowDelay } from '@/motion'

/**
 * Sub-Committees — modul CRUD, sebangun dengan Manage Category (`433:6116`).
 *
 * **Dulu satu formulir massal** yang mengirim seluruh daftar dan membuat ulang
 * tabelnya tiap Save. Kenapa itu diganti ada di `SubCommitteeController`; yang
 * berubah di layar adalah konsekuensinya: baris disunting DI TEMPAT, satu per
 * satu, dan tiap tindakan berdiri sendiri — menyimpan sebuah nama tidak
 * menyentuh baris lain, dan menaikkan sebuah baris tidak menuntut nama-nama
 * yang lain valid.
 *
 * Bentuknya mengikuti News Categories, bukan News Articles. Entitas ini dua
 * field; halaman formulir tersendiri untuk dua field berarti dua kali
 * berpindah halaman demi satu kata.
 *
 * Urutannya penting — situs publik mencetak kartunya sesuai `position` — jadi
 * panah naik/turun disimpan seketika lewat route-nya sendiri, bukan lewat
 * tombol "Save order" yang bisa ditinggalkan orang tanpa ditekan.
 */
interface Row {
    id: number
    name: string
    href: string | null
    isActive: boolean
    updatedAt: string | null
    updatedBy: string | null
}

const props = defineProps<{ committees: Row[] }>()

const { t } = useI18n()

/** Baris yang sedang disunting: id-nya, atau `'new'` untuk baris tambahan. */
const editing = ref<number | 'new' | null>(null)
const draftName = ref('')
const draftHref = ref('')
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const removing = ref<Row | null>(null)

const input = ref<HTMLInputElement | null>(null)

/**
 * Ada yang akan hilang kalau halaman ini ditinggalkan sekarang.
 *
 * Baris yang dibuka tapi belum diubah TIDAK dihitung — menekan pensil lalu
 * berpindah halaman bukan kehilangan apa pun, dan dialog yang muncul untuk itu
 * cuma mengajari orang menutupnya tanpa membaca.
 */
const hasUnsavedDraft = computed(() => {
    if (editing.value === null) return false
    if (editing.value === 'new') return draftName.value.trim() !== '' || draftHref.value.trim() !== ''

    const current = props.committees.find((c) => c.id === editing.value)

    return (
        draftName.value.trim() !== (current?.name ?? '') ||
        draftHref.value.trim() !== (current?.href ?? '')
    )
})

async function focusInput(): Promise<void> {
    await nextTick()
    input.value?.focus()
    input.value?.select()
}

function startEdit(row: Row): void {
    editing.value = row.id
    draftName.value = row.name
    draftHref.value = row.href ?? ''
    errors.value = {}
    focusInput()
}

function startAdd(): void {
    editing.value = 'new'
    draftName.value = ''
    draftHref.value = ''
    errors.value = {}
    focusInput()
}

function cancel(): void {
    editing.value = null
    draftName.value = ''
    draftHref.value = ''
    errors.value = {}
}

function save(): void {
    if (draftName.value.trim() === '') return

    saving.value = true
    errors.value = {}

    // Baris baru selalu aktif; baris yang disunting mempertahankan statusnya —
    // sakelarnya punya jalurnya sendiri dan tidak lewat mode sunting.
    const current = props.committees.find((c) => c.id === editing.value)
    const payload = {
        name: draftName.value.trim(),
        // Kosong berarti "belum ada halamannya", dan itu `null` di database —
        // bukan string kosong, yang akan tercetak sebagai tautan ke "".
        href: draftHref.value.trim() === '' ? null : draftHref.value.trim(),
        is_active: current?.isActive ?? true,
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => cancel(),
        // Ditampilkan di baris itu sendiri, bukan sebagai spanduk di atas
        // halaman yang tidak menunjuk baris mana yang bermasalah.
        onError: (received: Record<string, string>) => (errors.value = received),
        onFinish: () => (saving.value = false),
    }

    if (editing.value === 'new') {
        router.post('/people/sub-committees', payload, options)
        return
    }

    router.put(`/people/sub-committees/${editing.value}`, payload, options)
}

/** Sakelar status disimpan langsung — tidak perlu masuk mode sunting. */
function toggleStatus(row: Row, value: boolean): void {
    router.put(
        `/people/sub-committees/${row.id}`,
        { name: row.name, href: row.href, is_active: value },
        { preserveScroll: true },
    )
}

/**
 * Menukar sebuah baris dengan tetangganya, lalu menyimpan urutan barunya.
 *
 * Dikirim sebagai daftar id, bukan "pindahkan baris ini satu ke atas": urutan
 * yang dikirim utuh tidak bisa salah tafsir kalau ada orang lain yang baru saja
 * menambah baris di antaranya.
 */
function move(index: number, delta: number): void {
    const target = index + delta
    if (target < 0 || target >= props.committees.length) return

    const ids = props.committees.map((c) => c.id)
    ;[ids[index], ids[target]] = [ids[target]!, ids[index]!]

    router.patch('/people/sub-committees/order', { ids }, { preserveScroll: true })
}

function destroy(): void {
    if (!removing.value) return

    saving.value = true
    router.delete(`/people/sub-committees/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
            removing.value = null
        },
    })
}

const HEAD = 'bg-cool-10 px-3 py-4 text-left text-subtitle-s text-cool-100'
</script>

<template>
    <Head :title="t('people.sub_committees')" />

    <AdminLayout>
        <PageHeader
            :title="t('people.sub_committees')"
            :breadcrumbs="[
                { label: t('people.title') },
                { label: t('people.board'), href: '/people' },
                { label: t('people.sub_committees') },
            ]"
        />

        <ContextNote>{{ t('people.sub_hint') }}</ContextNote>

        <CardSection>
            <p class="text-body-s text-cool-60">{{ t('people.sub_href_hint') }}</p>

            <div class="w-full overflow-x-auto border border-cool-20">
                <table class="w-full border-collapse">
                    <thead>
                        <tr>
                            <th scope="col" :class="HEAD">{{ t('people.sub_name') }}</th>
                            <th scope="col" :class="HEAD">{{ t('people.sub_href') }}</th>
                            <th scope="col" :class="HEAD">{{ t('common.status') }}</th>
                            <th scope="col" :class="`w-[190px] ${HEAD}`">
                                <span class="sr-only">{{ t('common.actions') }}</span>
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Satu `AnimatePresence` untuk SELURUH isi tabel:
                             baris tambahan, baris data, dan keadaan kosong
                             saling menggantikan, dan hanya pengelola yang sama
                             yang bisa menganimasikan pergantian di antara
                             ketiganya. -->
                        <AnimatePresence>
                            <motion.tr
                                v-for="(row, index) in committees"
                                :key="row.id"
                                class="border-t border-cool-20"
                                :initial="{ opacity: 0, y: 8 }"
                                :animate="{ opacity: 1, y: 0 }"
                                :exit="{ opacity: 0, x: -12, transition: FADE }"
                                :transition="{ ...RISE, delay: rowDelay(index) }"
                            >
                                <td class="px-3 py-2">
                                    <!-- `mode="wait"` — field baru masuk SETELAH
                                         teksnya hilang; kalau menyeberang
                                         bersamaan ada satu momen dua nama
                                         terbaca bertumpuk di sel yang sama. -->
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
                                                v-model="draftName"
                                                type="text"
                                                class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90"
                                                :aria-label="t('people.sub_name')"
                                                :aria-invalid="errors.name ? 'true' : undefined"
                                                @keyup.enter="save"
                                                @keyup.esc="cancel"
                                            />
                                            <p v-if="errors.name" role="alert" class="mt-1 text-body-xs text-danger">
                                                {{ errors.name }}
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

                                <td class="px-3 py-2">
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
                                                v-model="draftHref"
                                                type="text"
                                                class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90 placeholder:text-cool-60"
                                                placeholder="/governance"
                                                :aria-label="t('people.sub_href')"
                                                :aria-invalid="errors.href ? 'true' : undefined"
                                                @keyup.enter="save"
                                                @keyup.esc="cancel"
                                            />
                                            <p v-if="errors.href" role="alert" class="mt-1 text-body-xs text-danger">
                                                {{ errors.href }}
                                            </p>
                                        </motion.div>
                                        <!-- Em dash, bukan sel kosong: "belum
                                             ada halamannya" adalah jawaban, dan
                                             sel kosong terbaca seperti sesuatu
                                             yang gagal dimuat. -->
                                        <motion.span
                                            v-else
                                            key="read"
                                            class="block text-body-s text-cool-60"
                                            :initial="{ opacity: 0 }"
                                            :animate="{ opacity: 1 }"
                                            :exit="{ opacity: 0 }"
                                            :transition="FADE"
                                        >
                                            {{ row.href ?? '—' }}
                                        </motion.span>
                                    </AnimatePresence>
                                </td>

                                <td class="px-3 py-2">
                                    <AppToggle
                                        :model-value="row.isActive"
                                        :label="`${t('common.status')} ${row.name}`"
                                        :disabled="editing !== null"
                                        hide-label
                                        @update:model-value="toggleStatus(row, $event)"
                                    />
                                </td>

                                <td class="px-3 py-2">
                                    <!-- Pasangan tombolnya bertukar dengan arah
                                         berlawanan — sunting/hapus keluar ke
                                         kanan, batal/simpan masuk dari kanan.
                                         Yang terbaca: satu set digantikan set
                                         lain, bukan empat tombol berkedip. -->
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
                                            <IconButton tone="danger" :label="t('category.cancel_row')" @click="cancel">
                                                <PhX :size="20" />
                                            </IconButton>
                                            <IconButton
                                                tone="success"
                                                :label="t('category.confirm_row')"
                                                :disabled="saving || draftName.trim() === ''"
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
                                                :label="t('people.sub_move_up', { name: row.name })"
                                                :disabled="index === 0 || editing !== null"
                                                @click="move(index, -1)"
                                            >
                                                <PhArrowUp :size="20" />
                                            </IconButton>
                                            <IconButton
                                                :label="t('people.sub_move_down', { name: row.name })"
                                                :disabled="index === committees.length - 1 || editing !== null"
                                                @click="move(index, 1)"
                                            >
                                                <PhArrowDown :size="20" />
                                            </IconButton>
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
                                                :disabled="editing !== null"
                                                @click="removing = row"
                                            >
                                                <PhTrash :size="20" />
                                            </IconButton>
                                        </motion.div>
                                    </AnimatePresence>
                                </td>
                            </motion.tr>

                            <!-- Baris tambahan muncul di UJUNG, bukan di atas,
                                 supaya urutan yang sudah dibaca orang tidak
                                 bergeser saat ia menambah. -->
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
                                        v-model="draftName"
                                        type="text"
                                        class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90 placeholder:text-cool-60"
                                        :placeholder="t('people.sub_name')"
                                        :aria-label="t('people.sub_name')"
                                        :aria-invalid="errors.name ? 'true' : undefined"
                                        @keyup.enter="save"
                                        @keyup.esc="cancel"
                                    />
                                    <p v-if="errors.name" role="alert" class="mt-1 text-body-xs text-danger">
                                        {{ errors.name }}
                                    </p>
                                </td>

                                <td class="px-3 py-2">
                                    <input
                                        v-model="draftHref"
                                        type="text"
                                        class="h-12 w-full border-b border-cool-30 bg-cool-10 px-4 text-body-m text-cool-90 placeholder:text-cool-60"
                                        placeholder="/governance"
                                        :aria-label="t('people.sub_href')"
                                        :aria-invalid="errors.href ? 'true' : undefined"
                                        @keyup.enter="save"
                                        @keyup.esc="cancel"
                                    />
                                    <p v-if="errors.href" role="alert" class="mt-1 text-body-xs text-danger">
                                        {{ errors.href }}
                                    </p>
                                </td>

                                <td class="px-3 py-2" />

                                <td class="px-3 py-2">
                                    <div class="flex items-center justify-end gap-2">
                                        <IconButton tone="danger" :label="t('category.cancel_row')" @click="cancel">
                                            <PhX :size="20" />
                                        </IconButton>
                                        <IconButton
                                            tone="success"
                                            :label="t('category.confirm_row')"
                                            :disabled="saving || draftName.trim() === ''"
                                            @click="save"
                                        >
                                            <PhCheck :size="20" />
                                        </IconButton>
                                    </div>
                                </td>
                            </motion.tr>

                            <motion.tr
                                v-if="committees.length === 0 && editing !== 'new'"
                                key="empty"
                                :initial="{ opacity: 0 }"
                                :animate="{ opacity: 1 }"
                                :exit="{ opacity: 0 }"
                                :transition="FADE"
                            >
                                <td
                                    colspan="4"
                                    class="border-t border-cool-20 px-3 py-8 text-center text-body-s text-cool-60"
                                >
                                    {{ t('people.sub_empty') }}
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
                {{ t('people.add_sub') }}
            </motion.button>
        </CardSection>

        <ConfirmDialog
            :open="removing !== null"
            variant="deletion"
            :title="t('people.sub_delete_title')"
            :description="t('people.sub_delete_body', { name: removing?.name ?? '' })"
            :confirm-label="t('common.delete')"
            :processing="saving"
            @confirm="destroy"
            @cancel="removing = null"
        />

        <UnsavedGuard :dirty="hasUnsavedDraft" />
    </AdminLayout>
</template>
