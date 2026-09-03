<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { PhCaretRight } from '@phosphor-icons/vue'
import type { Crumb } from '@/types'
import { useI18n } from '@/composables/useI18n'

/**
 * Komponen Figma `Breadcrumbs` (`251:1218`) — ruas terakhir berwarna
 * Primary/90 dan tidak bisa diklik, sisanya CoolGray/60.
 */
defineProps<{ items: Crumb[] }>()

const { t } = useI18n()
</script>

<template>
    <nav :aria-label="t('nav.breadcrumb')">
        <ol class="flex items-center gap-2">
            <li v-for="(item, index) in items" :key="item.label" class="flex items-center gap-2">
                <PhCaretRight v-if="index > 0" :size="24" class="text-cool-60" aria-hidden="true" />

                <component
                    :is="item.href && index < items.length - 1 ? Link : 'span'"
                    :href="item.href"
                    class="text-body-s"
                    :class="
                        index === items.length - 1
                            ? 'text-primary-90'
                            : 'text-cool-60 hover:text-cool-90'
                    "
                    :aria-current="index === items.length - 1 ? 'page' : undefined"
                >
                    {{ item.label }}
                </component>
            </li>
        </ol>
    </nav>
</template>
