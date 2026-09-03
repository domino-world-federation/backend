<script setup lang="ts">
import { computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import { useI18n } from '@/composables/useI18n'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import CategoryForm from '@/Components/CategoryForm.vue'

const props = defineProps<{
    category: { id: number; name: string; isActive: boolean } | null
}>()

const { t } = useI18n()

const title = computed(() =>
    props.category ? `${t('common.edit')} ${t('faq.category_title')}` : `${t('category.add')} — ${t('faq.category_title')}`,
)
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <CategoryForm
            :title="title"
            :breadcrumbs="[
                { label: t('faq.title'), href: '/faq' },
                { label: t('common.category'), href: '/faq/categories' },
                { label: title },
            ]"
            base-url="/faq/categories"
            :status-hint="t('faq.category_status_hint')"
            :category="category"
        />
    </AdminLayout>
</template>
