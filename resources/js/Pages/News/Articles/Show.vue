<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { PhArrowLeft, PhPencilSimple, PhStar } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import AppButton from '@/Components/AppButton.vue'
import StatusPill from '@/Components/StatusPill.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { useI18n } from '@/composables/useI18n'
import { formatDateTime } from '@/utils/format'

/**
 * Layar baca satu artikel — dibuka dari judul di daftar berita.
 *
 * Ia sengaja BUKAN formulir yang dinonaktifkan. Yang dicari orang di sini
 * cuma satu hal ("betul tidak isinya?"), dan formulir yang diredupkan tetap
 * meminta mata menyaring label, hint, dan tombol yang tidak sedang dipakai.
 */
const props = defineProps<{
    article: {
        id: number
        title: string
        slug: string
        excerpt: string | null
        body: string | null
        category: string | null
        visibility: string
        isHighlighted: boolean
        authorName: string | null
        publishedAt: string | null
        createdAt: string | null
        updatedAt: string | null
        heroUrl: string | null
        landscapeUrl: string | null
    }
}>()

const { t } = useI18n()

const removing = ref(false)
const processing = ref(false)

function destroy(): void {
    processing.value = true
    router.delete(`/news/${props.article.id}`, {
        onFinish: () => {
            processing.value = false
            removing.value = false
        },
    })
}

const META = computed(() => [
    { label: t('common.category'), value: props.article.category ?? t('common.none') },
    { label: t('news.author'), value: props.article.authorName ?? t('common.none') },
    { label: t('news.posted'), value: formatDateTime(props.article.publishedAt) },
    { label: t('news.created'), value: formatDateTime(props.article.createdAt) },
    { label: t('news.last_modified'), value: formatDateTime(props.article.updatedAt) },
    { label: t('news.field_slug'), value: props.article.slug },
])

const images = computed(() =>
    [
        { label: t('news.image_hero'), url: props.article.heroUrl },
        { label: t('news.image_landscape'), url: props.article.landscapeUrl },
    ].filter((image): image is { label: string; url: string } => image.url !== null),
)
</script>

<template>
    <Head :title="article.title" />

    <AdminLayout>
        <PageHeader
            :title="t('news.detail')"
            :breadcrumbs="[
                { label: t('news.title'), href: '/news' },
                { label: t('news.list'), href: '/news' },
                { label: article.title },
            ]"
        >
            <template #actions>
                <AppButton href="/news" variant="outline">
                    <template #iconLeft><PhArrowLeft :size="24" /></template>
                    {{ t('news.back_to_list') }}
                </AppButton>
                <AppButton :href="`/news/${article.id}/edit`">
                    <template #iconLeft><PhPencilSimple :size="24" /></template>
                    {{ t('common.edit') }}
                </AppButton>
            </template>
        </PageHeader>

        <CardSection>
            <div class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <StatusPill :value="article.visibility" />
                    <span
                        v-if="article.isHighlighted"
                        class="inline-flex items-center gap-1 border border-primary-60 px-2 py-1 text-body-xs text-cool-90"
                    >
                        <PhStar :size="14" weight="fill" class="text-primary-60" aria-hidden="true" />
                        {{ t('news.highlight_badge') }}
                    </span>
                </div>

                <h1 class="text-heading-4 text-cool-100">{{ article.title }}</h1>

                <p v-if="article.excerpt" class="text-body-m text-cool-70">{{ article.excerpt }}</p>
            </div>

            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="field in META" :key="field.label" class="flex flex-col gap-1">
                    <dt class="text-body-xs text-cool-60">{{ field.label }}</dt>
                    <dd class="text-body-s text-cool-90">{{ field.value }}</dd>
                </div>
            </dl>
        </CardSection>

        <CardSection :title="t('news.images')">
            <p v-if="images.length === 0" class="text-body-s text-cool-60">
                {{ t('news.no_images') }}
            </p>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <figure v-for="image in images" :key="image.label" class="flex flex-col gap-2">
                    <img
                        :src="image.url"
                        :alt="`${image.label} — ${article.title}`"
                        class="w-full border border-cool-20 bg-cool-10 object-cover"
                    />
                    <figcaption class="text-body-xs text-cool-60">{{ image.label }}</figcaption>
                </figure>
            </div>
        </CardSection>

        <CardSection :title="t('news.field_content')">
            <p v-if="!article.body" class="text-body-s text-cool-60">{{ t('news.no_content') }}</p>

            <!--
                `v-html` di sini aman DAN disengaja: isinya sudah dilewatkan
                `Purifier::clean()` saat disimpan (lihat `NewsArticleController::fill`),
                dan artikel yang dirender sebagai teks mentah tidak menjawab
                pertanyaan yang membawa orang ke halaman ini — "sudah benar
                belum tampilannya".
            -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div v-else class="prose-dwf" v-html="article.body" />
        </CardSection>

        <div class="flex justify-end">
            <AppButton variant="outline" @click="removing = true">{{ t('common.delete') }}</AppButton>
        </div>

        <ConfirmDialog
            :open="removing"
            variant="deletion"
            :title="t('news.delete_title')"
            :description="t('news.delete_body', { name: article.title })"
            :confirm-label="t('common.delete')"
            :processing="processing"
            @confirm="destroy"
            @cancel="removing = false"
        />
    </AdminLayout>
</template>
