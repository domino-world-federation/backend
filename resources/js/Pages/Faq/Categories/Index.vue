<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CategoryIndex from '@/Components/CategoryIndex.vue'
import type { Paginated } from '@/types'

defineProps<{
    categories: Paginated<{ id: number; name: string; usage: number; isActive: boolean }>
    filters: { q: string; status: string }
}>()

const { t } = useI18n()
</script>

<template>
    <Head :title="t('faq.category_title')" />

    <AdminLayout>
        <CategoryIndex
            :title="t('faq.category_title')"
            :breadcrumbs="[{ label: t('faq.title'), href: '/faq' }, { label: t('faq.category_title') }]"
            base-url="/faq/categories"
            :categories="categories"
            :filters="filters"
        />
    </AdminLayout>
</template>
