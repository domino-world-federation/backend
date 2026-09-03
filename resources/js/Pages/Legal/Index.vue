<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import { PhCaretRight } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import { formatDate } from '@/utils/format'

defineProps<{
    pages: Array<{
        key: string
        title: string
        slug: string
        blocks: number
        lastUpdatedAt: string | null
        href: string
    }>
}>()

const { t } = useI18n()
</script>

<template>
    <Head :title="t('legal.title')" />

    <AdminLayout>
        <PageHeader
            :title="t('legal.title')"
            :breadcrumbs="[{ label: t('settings.site') }, { label: t('legal.title') }]"
        >
            <template #description>
{{ t('legal.intro') }}
            </template>
        </PageHeader>

        <CardSection :title="t('legal.pages')">
            <ul class="flex flex-col">
                <li v-for="page in pages" :key="page.key" class="border-t border-cool-20 first:border-t-0">
                    <Link
                        :href="page.href"
                        class="flex items-center gap-3 py-4 transition-colors hover:bg-cool-10"
                    >
                        <span class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="text-body-m text-cool-90">{{ t(page.key === 'terms' ? 'legal.terms' : 'legal.privacy') }}</span>
                            <span class="text-body-xs text-cool-60">
                                {{
                                    t('legal.summary', {
                                        slug: page.slug,
                                        count: page.blocks,
                                        date: formatDate(page.lastUpdatedAt),
                                    })
                                }}
                            </span>
                        </span>
                        <PhCaretRight :size="16" class="shrink-0 text-cool-40" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </CardSection>
    </AdminLayout>
</template>
