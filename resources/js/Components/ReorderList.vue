<script setup lang="ts">
import { ref, watch } from 'vue'
import { PhArrowDown, PhArrowUp, PhDotsSixVertical } from '@phosphor-icons/vue'
import { useI18n } from '@/composables/useI18n'

/**
 * Daftar yang urutannya bisa diubah (`433:6170`, `343:4961`).
 *
 * Menyediakan DUA cara memindahkan baris: seret-lepas dan sepasang tombol
 * naik/turun. Tombolnya bukan pelengkap — seret-lepas mustahil dipakai dengan
 * keyboard dan berat dipakai dengan pembaca layar, jadi tanpa tombol itu fitur
 * ini hanya ada untuk sebagian orang.
 *
 * Urutannya disimpan di state lokal dan baru dikirim saat tombol Simpan
 * ditekan, supaya satu kali seret tidak jadi satu request.
 */
const props = defineProps<{
    /**
     * `note` opsional: keterangan kecil di sebelah label — kategori sebuah FAQ,
     * misalnya. Ada karena menyeret baris di daftar panjang tanpa tahu baris
     * itu MILIK apa adalah menyeret buta; yang tergeser bukan cuma yang
     * kelihatan.
     */
    items: Array<{ id: number; label: string; note?: string | null }>
}>()

const { t } = useI18n()
const emit = defineEmits<{ change: [ids: number[]] }>()

const rows = ref([...props.items])
const dragging = ref<number | null>(null)

watch(
    () => props.items,
    (value) => (rows.value = [...value]),
)

function move(from: number, to: number): void {
    if (to < 0 || to >= rows.value.length || from === to) return

    const next = [...rows.value]
    const [moved] = next.splice(from, 1)
    next.splice(to, 0, moved)
    rows.value = next

    emit('change', next.map((r) => r.id))
}

function onDrop(index: number): void {
    if (dragging.value === null) return
    move(dragging.value, index)
    dragging.value = null
}
</script>

<template>
    <ul class="flex flex-col border border-cool-20 bg-surface">
        <li
            v-for="(row, index) in rows"
            :key="row.id"
            class="flex items-center gap-3 border-t border-cool-20 px-3 py-2 first:border-t-0"
            :class="dragging === index ? 'opacity-50' : ''"
            draggable="true"
            @dragstart="dragging = index"
            @dragend="dragging = null"
            @dragover.prevent
            @drop.prevent="onDrop(index)"
        >
            <PhDotsSixVertical
                :size="20"
                class="shrink-0 cursor-grab text-cool-40"
                aria-hidden="true"
            />

            <span class="flex min-w-0 flex-1 items-center gap-2">
                <span class="min-w-0 truncate text-body-s text-cool-100">{{ row.label }}</span>
                <span
                    v-if="row.note"
                    class="shrink-0 border border-cool-20 px-2 py-0.5 text-body-xs text-cool-60"
                >{{ row.note }}</span>
            </span>

            <span class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    class="flex size-8 cursor-pointer items-center justify-center text-cool-70 disabled:cursor-not-allowed disabled:text-cool-30"
                    :disabled="index === 0"
                    :aria-label="t('order.move_up', { name: row.label })"
                    @click="move(index, index - 1)"
                >
                    <PhArrowUp :size="16" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="flex size-8 cursor-pointer items-center justify-center text-cool-70 disabled:cursor-not-allowed disabled:text-cool-30"
                    :disabled="index === rows.length - 1"
                    :aria-label="t('order.move_down', { name: row.label })"
                    @click="move(index, index + 1)"
                >
                    <PhArrowDown :size="16" aria-hidden="true" />
                </button>

                <!-- Tindakan tambahan per baris (mis. "keluarkan dari halaman
                     ini"). Slot, bukan prop: apa yang boleh dilakukan pada satu
                     baris urusan layar yang memakainya. -->
                <slot name="rowActions" :row="row" :index="index" />
            </span>
        </li>

        <li v-if="rows.length === 0" class="px-3 py-6 text-center text-body-s text-cool-60">
            {{ t('order.empty') }}
        </li>
    </ul>
</template>
