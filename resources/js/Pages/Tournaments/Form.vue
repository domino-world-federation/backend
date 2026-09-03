<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { PhArrowDown, PhArrowUp, PhPlus, PhTrash } from '@phosphor-icons/vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import CardSection from '@/Components/CardSection.vue'
import FormRow from '@/Components/FormRow.vue'
import AppField from '@/Components/AppField.vue'
import AppRadio from '@/Components/AppRadio.vue'
import AppCheckbox from '@/Components/AppCheckbox.vue'
import AppButton from '@/Components/AppButton.vue'
import SelectField from '@/Components/SelectField.vue'
import MediaUpload from '@/Components/MediaUpload.vue'
import ContextNote from '@/Components/ContextNote.vue'
import ProgressSteps from '@/Components/ProgressSteps.vue'
import UnsavedGuard from '@/Components/UnsavedGuard.vue'
import RichTextEditor from '@/Components/Editor/RichTextEditor.vue'
import { useI18n } from '@/composables/useI18n'

interface Official {
    id?: number
    name: string
    role: string
    country: string
    photo: File | null
    photoUrl?: string | null
}

interface ScheduleItem {
    held_on: string
    starts_at: string
    activity: string
    area: string
}

const props = defineProps<{
    tournament: Record<string, any> | null
    options: {
        coverage: string[]
        rulesFormats: string[]
        attendance: string[]
        participantTypes: string[]
        currencies: string[]
        dwfIdRequirements: string[]
        eligibility: string[]
        registrationMethods: string[]
        maxDocuments: number
    }
    documentOptions: Array<{ value: number; label: string; category: string | null }>
}>()

const { t } = useI18n()

const isEdit = props.tournament !== null
const pageTitle = computed(() => (isEdit ? t('tournaments.edit') : t('tournaments.add')))

const form = useForm({
    name: props.tournament?.name ?? '',
    slug: props.tournament?.slug ?? '',
    coverage: props.tournament?.coverage ?? null,
    starts_on: props.tournament?.startsOn ?? '',
    ends_on: props.tournament?.endsOn ?? '',
    city: props.tournament?.city ?? '',
    country: props.tournament?.country ?? '',
    rules_format: props.tournament?.rulesFormat ?? null,
    attendance: props.tournament?.attendance ?? 'Offline',
    hero_image: null as File | null,
    overview: props.tournament?.overview ?? '',

    venue_name: props.tournament?.venueName ?? '',
    venue_address: props.tournament?.venueAddress ?? '',
    venue_lat: props.tournament?.venueLat ?? '',
    venue_lng: props.tournament?.venueLng ?? '',

    prize_amount: props.tournament?.prizeAmount ?? '',
    prize_currency: props.tournament?.prizeCurrency ?? null,
    prize_description: props.tournament?.prizeDescription ?? '',
    prize_image: null as File | null,

    contact_email: props.tournament?.contactEmail ?? '',
    contact_phone: props.tournament?.contactPhone ?? '',

    officials: ((props.tournament?.officials ?? []) as Official[]).map((o) => ({
        id: o.id,
        name: o.name,
        role: o.role,
        country: o.country,
        photo: null as File | null,
        photoUrl: o.photoUrl ?? null,
    })),

    registration_starts_on: props.tournament?.registrationStartsOn ?? '',
    registration_ends_on: props.tournament?.registrationEndsOn ?? '',
    dwf_id_requirement: props.tournament?.dwfIdRequirement ?? null,
    eligibility: props.tournament?.eligibility ?? null,
    registration_method: props.tournament?.registrationMethod ?? null,

    schedule: ((props.tournament?.schedule ?? []) as ScheduleItem[]).map((s) => ({ ...s, area: s.area ?? '' })),

    game_format: props.tournament?.gameFormat ?? '',
    participant_count: props.tournament?.participantCount ?? '',
    participant_type: props.tournament?.participantType ?? null,
    competition_system: props.tournament?.competitionSystem ?? '',
    scoring: props.tournament?.scoring ?? '',

    documents: [...((props.tournament?.documents ?? []) as number[])],

    posting: props.tournament?.status === 'scheduled' ? 'schedule' : 'now',
    published_at: props.tournament?.publishedAt ?? '',
})

const canSchedule = computed(() => form.posting === 'schedule')

/* Jadwal terbit dipecah jadi jam dan tanggal, satu `published_at` yang dikirim
   — pola yang sama dengan News, Gallery, dan Documents. */
const initial = props.tournament?.publishedAt ?? ''
const scheduleDate = ref(initial.slice(0, 10))
const scheduleTime = ref(initial.slice(11, 16))

function syncPublishedAt(): void {
    form.published_at = scheduleDate.value && scheduleTime.value
        ? `${scheduleDate.value}T${scheduleTime.value}`
        : ''
}

function toOptions(values: string[]) {
    return values.map((v) => ({ value: v, label: v }))
}

/*
 * Sidebar Progress Step — `585:11561`.
 *
 * Delapan langkah, bukan sepuluh: "Tournament Contact" dan "Tournament
 * Overview" tidak ada di stepper desain (`594:11539`) — yang pertama karena
 * seluruhnya opsional, yang kedua karena Overview digambar DI DALAM Basic
 * Information (`596:11005`) dan section terpisahnya (`596:11291`) isinya sama
 * persis.
 *
 * ── Yang dihitung cincinnya: field WAJIB saja. ──
 *
 * Ia menjawab "berapa banyak lagi sebelum ini bisa disimpan", bukan "berapa
 * banyak kotak yang ada". Section yang seluruhnya opsional — Prize dan
 * Regulations — karena itu memakai ukurannya sendiri: penuh begitu ada satu
 * isian, nol kalau kosong. Cincin yang tidak pernah bisa penuh mengajari orang
 * mengabaikannya.
 */
function filledCount(...values: unknown[]): number {
    return values.filter((v) => v !== null && v !== undefined && v !== '').length
}

function ratio(done: number, total: number): number {
    return total === 0 ? 0 : done / total
}

const progress = computed(() => ({
    basic: ratio(
        filledCount(
            form.name, form.coverage, form.starts_on, form.ends_on,
            form.city, form.country, form.rules_format, form.attendance, form.overview,
        ) + (form.hero_image || props.tournament?.heroImageUrl ? 1 : 0),
        10,
    ),
    venue: ratio(filledCount(form.venue_name, form.venue_address, form.venue_lat, form.venue_lng), 4),
    prize: filledCount(
        form.prize_amount, form.prize_description, form.prize_image, props.tournament?.prizeImageUrl,
    ) > 0 ? 1 : 0,
    officials: form.officials.length === 0
        ? 0
        : ratio(
            form.officials.filter((o) => o.name !== '' && o.role !== '' && o.country !== '').length,
            form.officials.length,
        ),
    eligibility: ratio(filledCount(form.eligibility, form.registration_method), 2),
    schedule: form.schedule.length === 0
        ? 0
        : ratio(
            form.schedule.filter((i) => i.held_on !== '' && i.starts_at !== '' && i.activity !== '').length,
            form.schedule.length,
        ),
    format: ratio(
        filledCount(form.game_format, form.participant_type, form.competition_system, form.scoring),
        4,
    ),
    regulations: form.documents.length > 0 ? 1 : 0,
}))

const steps = computed(() => [
    { id: 'basic', label: t('tournaments.section_basic'), progress: progress.value.basic },
    { id: 'venue', label: t('tournaments.section_venue'), progress: progress.value.venue },
    { id: 'prize', label: t('tournaments.section_prize'), progress: progress.value.prize },
    { id: 'officials', label: t('tournaments.section_officials'), progress: progress.value.officials },
    { id: 'eligibility', label: t('tournaments.section_eligibility'), progress: progress.value.eligibility },
    { id: 'schedule', label: t('tournaments.section_schedule'), progress: progress.value.schedule },
    { id: 'format', label: t('tournaments.section_format'), progress: progress.value.format },
    { id: 'regulations', label: t('tournaments.section_regulations'), progress: progress.value.regulations },
])

// --- kelompok berulang ---------------------------------------------------

function addOfficial(): void {
    // `id: undefined` ditulis eksplisit: baris baru belum punya barisnya di
    // database, dan controller memakai ketiadaan id itu untuk tahu bahwa tidak
    // ada foto lama yang perlu dipertahankan.
    form.officials = [
        ...form.officials,
        { id: undefined, name: '', role: '', country: '', photo: null, photoUrl: null },
    ]
}

function removeOfficial(index: number): void {
    form.officials = form.officials.filter((_, i) => i !== index)
}

function addScheduleItem(): void {
    form.schedule = [...form.schedule, { held_on: '', starts_at: '', activity: '', area: '' }]
}

function removeScheduleItem(index: number): void {
    form.schedule = form.schedule.filter((_, i) => i !== index)
}

/** "Admin can add, remove, and reorder schedule items" (`596:11361`). */
function moveScheduleItem(index: number, delta: number): void {
    const target = index + delta
    if (target < 0 || target >= form.schedule.length) return

    const next = [...form.schedule]
    ;[next[index], next[target]] = [next[target], next[index]]
    form.schedule = next
}

function toggleDocument(id: number, checked: boolean): void {
    form.documents = checked
        ? [...form.documents, id]
        : form.documents.filter((d) => d !== id)
}

/** Batas "up to 10" dijaga di UI juga, bukan cuma di server. */
const documentLimitReached = computed(() => form.documents.length >= props.options.maxDocuments)

function submit(posting: 'draft' | 'now' | 'schedule'): void {
    form.posting = posting === 'draft' ? 'draft' : posting

    // Berkas ada di beberapa tempat sekaligus (hero, hadiah, tiap foto ofisial),
    // jadi seluruh kiriman harus multipart.
    const options = { forceFormData: true }

    if (isEdit) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            `/tournaments/${props.tournament!.id}`,
            options,
        )
        return
    }

    form.post('/tournaments', options)
}
</script>

<template>
    <Head :title="pageTitle" />

    <AdminLayout>
        <PageHeader
            :title="pageTitle"
            :breadcrumbs="[
                { label: t('tournaments.title') },
                { label: t('tournaments.list'), href: '/tournaments' },
                { label: pageTitle },
            ]"
        />

        <form class="flex flex-col gap-6" @submit.prevent="submit('now')">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                <!-- `585:11561`. Ia menaut, bukan menggantikan langkah:
                     seluruh formulir tetap satu halaman, jadi orang bisa
                     melompat tanpa kehilangan isian di section lain. `sticky`
                     supaya ia ikut selama menggulir. -->
                <div class="hidden shrink-0 lg:sticky lg:top-20 lg:block">
                    <ProgressSteps :steps="steps" :label="t('tournaments.progress')" />
                </div>

                <div class="flex min-w-0 flex-1 flex-col items-end gap-4">
                    <!-- ============================= Basic Information -->
                    <CardSection id="section-basic" :title="t('tournaments.section_basic')">
                        <FormRow :label="t('tournaments.name')" :description="t('tournaments.name_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.name" :error="form.errors.name" autofocus />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.coverage')" :description="t('tournaments.coverage_hint')" required>
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.coverage"
                                    :options="toOptions(options.coverage)"
                                    :error="form.errors.coverage"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.starts_on')" :description="t('tournaments.starts_on_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.starts_on" type="date" :error="form.errors.starts_on" />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.ends_on')" :description="t('tournaments.ends_on_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.ends_on" type="date" :error="form.errors.ends_on" />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.city')" :description="t('tournaments.city_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.city" :error="form.errors.city" />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.country')" :description="t('tournaments.country_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.country" :error="form.errors.country" />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.rules_format')"
                            :description="t('tournaments.rules_format_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.rules_format"
                                    :options="toOptions(options.rulesFormats)"
                                    :error="form.errors.rules_format"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.attendance')"
                            :description="t('tournaments.attendance_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.attendance"
                                    :options="toOptions(options.attendance)"
                                    :error="form.errors.attendance"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.hero_image')"
                            :description="t('tournaments.hero_image_hint')"
                            :required="!isEdit"
                        >
                            <template #default="{ id }">
                                <MediaUpload
                                    :id="id"
                                    v-model="form.hero_image"
                                    kind="image"
                                    :existing-url="tournament?.heroImageUrl"
                                    :error="form.errors.hero_image"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('news.field_slug')" :description="t('tournaments.slug_hint')">
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.slug" :error="form.errors.slug" />
                            </template>
                        </FormRow>

                        <!-- Overview digambar di DALAM Basic Information
                             (`596:11005`); section terpisahnya (`596:11291`)
                             isinya sama persis, jadi tidak dibangun dua kali. -->
                        <FormRow :label="t('tournaments.overview')" :description="t('tournaments.overview_hint')" required>
                            <template #default="{ id }">
                                <RichTextEditor :id="id" v-model="form.overview" :error="form.errors.overview" />
                            </template>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Venue -->
                    <CardSection id="section-venue" :title="t('tournaments.section_venue')">
                        <FormRow :label="t('tournaments.venue_name')" :description="t('tournaments.venue_name_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.venue_name" :error="form.errors.venue_name" />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.venue_address')"
                            :description="t('tournaments.venue_address_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.venue_address" textarea :error="form.errors.venue_address" />
                            </template>
                        </FormRow>

                        <!-- Dua kotak, bukan satu "Latitude, Longitude": satu
                             kotak berarti mengurai teks bebas, dan yang gagal
                             diurai berakhir sebagai pin di tengah laut. -->
                        <FormRow :label="t('tournaments.map_location')" :description="t('tournaments.map_location_hint')" required>
                            <div class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2">
                                <AppField
                                    v-model="form.venue_lat"
                                    type="number"
                                    step="0.0000001"
                                    :aria-label="t('tournaments.latitude')"
                                    placeholder="-6.2088"
                                    :error="form.errors.venue_lat"
                                />
                                <AppField
                                    v-model="form.venue_lng"
                                    type="number"
                                    step="0.0000001"
                                    :aria-label="t('tournaments.longitude')"
                                    placeholder="106.8456"
                                    :error="form.errors.venue_lng"
                                />
                            </div>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Prize -->
                    <CardSection id="section-prize" :title="t('tournaments.section_prize')">
                        <FormRow :label="t('tournaments.prize_amount')" :description="t('tournaments.prize_amount_hint')">
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.prize_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    placeholder="50000"
                                    :error="form.errors.prize_amount"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.prize_currency')" :description="t('tournaments.prize_currency_hint')">
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.prize_currency"
                                    :options="toOptions(options.currencies)"
                                    :error="form.errors.prize_currency"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.prize_description')"
                            :description="t('tournaments.prize_description_hint')"
                        >
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.prize_description"
                                    textarea
                                    :error="form.errors.prize_description"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.prize_image')" :description="t('tournaments.prize_image_hint')">
                            <template #default="{ id }">
                                <MediaUpload
                                    :id="id"
                                    v-model="form.prize_image"
                                    kind="image"
                                    :existing-url="tournament?.prizeImageUrl"
                                    :error="form.errors.prize_image"
                                />
                            </template>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Contact -->
                    <CardSection :title="t('tournaments.section_contact')">
                        <FormRow :label="t('tournaments.contact_email')" :description="t('tournaments.contact_email_hint')">
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.contact_email"
                                    type="email"
                                    placeholder="contact@dwf-domino.org"
                                    :error="form.errors.contact_email"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.contact_phone')" :description="t('tournaments.contact_phone_hint')">
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.contact_phone"
                                    placeholder="+41 21 032 320 00"
                                    :error="form.errors.contact_phone"
                                />
                            </template>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Officials -->
                    <CardSection id="section-officials" :title="t('tournaments.section_officials')">
                        <p v-if="form.officials.length === 0" class="text-body-s text-cool-60">
                            {{ t('tournaments.officials_empty') }}
                        </p>

                        <div
                            v-for="(official, index) in form.officials"
                            :key="index"
                            class="flex w-full flex-col gap-4 border-b border-cool-20 pb-4 last:border-b-0"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-subtitle-s text-cool-90">#{{ index + 1 }}</span>
                                <button
                                    type="button"
                                    class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                                    :aria-label="t('tournaments.remove_official', { name: official.name || `#${index + 1}` })"
                                    @click="removeOfficial(index)"
                                >
                                    <PhTrash :size="16" aria-hidden="true" />
                                    {{ t('common.delete') }}
                                </button>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <AppField
                                    v-model="official.name"
                                    :aria-label="t('tournaments.official_name')"
                                    :placeholder="t('tournaments.official_name')"
                                    :error="(form.errors as any)[`officials.${index}.name`]"
                                />
                                <AppField
                                    v-model="official.role"
                                    :aria-label="t('tournaments.official_role')"
                                    :placeholder="t('tournaments.official_role')"
                                    :error="(form.errors as any)[`officials.${index}.role`]"
                                />
                                <AppField
                                    v-model="official.country"
                                    :aria-label="t('tournaments.official_country')"
                                    :placeholder="t('tournaments.official_country')"
                                    :error="(form.errors as any)[`officials.${index}.country`]"
                                />
                            </div>

                            <MediaUpload
                                v-model="official.photo"
                                kind="image"
                                :existing-url="official.photoUrl"
                                :error="(form.errors as any)[`officials.${index}.photo`]"
                            />
                        </div>

                        <AppButton variant="outline" size="s" @click="addOfficial">
                            <template #iconLeft><PhPlus :size="20" /></template>
                            {{ t('tournaments.add_official') }}
                        </AppButton>
                    </CardSection>

                    <!-- ============================= Eligibility -->
                    <CardSection id="section-eligibility" :title="t('tournaments.section_eligibility')">
                        <FormRow
                            :label="t('tournaments.registration_starts_on')"
                            :description="t('tournaments.registration_starts_on_hint')"
                        >
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.registration_starts_on"
                                    type="date"
                                    :error="form.errors.registration_starts_on"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.registration_ends_on')"
                            :description="t('tournaments.registration_ends_on_hint')"
                        >
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.registration_ends_on"
                                    type="date"
                                    :error="form.errors.registration_ends_on"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.dwf_id_requirement')"
                            :description="t('tournaments.dwf_id_requirement_hint')"
                        >
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.dwf_id_requirement"
                                    :options="toOptions(options.dwfIdRequirements)"
                                    :error="form.errors.dwf_id_requirement"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.eligibility')" :description="t('tournaments.eligibility_hint')" required>
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.eligibility"
                                    :options="toOptions(options.eligibility)"
                                    :error="form.errors.eligibility"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.registration_method')"
                            :description="t('tournaments.registration_method_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.registration_method"
                                    :options="toOptions(options.registrationMethods)"
                                    :error="form.errors.registration_method"
                                />
                            </template>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Schedule -->
                    <CardSection id="section-schedule" :title="t('tournaments.section_schedule')">
                        <p class="text-body-xs text-cool-60">{{ t('tournaments.schedule_hint') }}</p>

                        <p v-if="form.schedule.length === 0" class="text-body-s text-cool-60">
                            {{ t('tournaments.schedule_empty') }}
                        </p>

                        <div
                            v-for="(item, index) in form.schedule"
                            :key="index"
                            class="flex w-full flex-col gap-3 border-b border-cool-20 pb-4 last:border-b-0"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-subtitle-s text-cool-90">#{{ index + 1 }}</span>
                                <div class="flex items-center gap-3">
                                    <button
                                        type="button"
                                        class="cursor-pointer text-cool-60 disabled:cursor-not-allowed disabled:text-cool-30"
                                        :disabled="index === 0"
                                        :aria-label="t('tournaments.move_up')"
                                        @click="moveScheduleItem(index, -1)"
                                    >
                                        <PhArrowUp :size="18" />
                                    </button>
                                    <button
                                        type="button"
                                        class="cursor-pointer text-cool-60 disabled:cursor-not-allowed disabled:text-cool-30"
                                        :disabled="index === form.schedule.length - 1"
                                        :aria-label="t('tournaments.move_down')"
                                        @click="moveScheduleItem(index, 1)"
                                    >
                                        <PhArrowDown :size="18" />
                                    </button>
                                    <button
                                        type="button"
                                        class="flex cursor-pointer items-center gap-1 text-body-xs text-danger"
                                        :aria-label="t('tournaments.remove_schedule', { name: item.activity || `#${index + 1}` })"
                                        @click="removeScheduleItem(index)"
                                    >
                                        <PhTrash :size="16" aria-hidden="true" />
                                        {{ t('common.delete') }}
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <AppField
                                    v-model="item.held_on"
                                    type="date"
                                    :aria-label="t('tournaments.schedule_date')"
                                    :error="(form.errors as any)[`schedule.${index}.held_on`]"
                                />
                                <AppField
                                    v-model="item.starts_at"
                                    type="time"
                                    :aria-label="t('tournaments.schedule_time')"
                                    :error="(form.errors as any)[`schedule.${index}.starts_at`]"
                                />
                                <AppField
                                    v-model="item.activity"
                                    :aria-label="t('tournaments.schedule_activity')"
                                    :placeholder="t('tournaments.schedule_activity')"
                                    :error="(form.errors as any)[`schedule.${index}.activity`]"
                                />
                                <AppField
                                    v-model="item.area"
                                    :aria-label="t('tournaments.schedule_area')"
                                    placeholder="Madrid Arena • Main Hall"
                                    :error="(form.errors as any)[`schedule.${index}.area`]"
                                />
                            </div>
                        </div>

                        <AppButton variant="outline" size="s" @click="addScheduleItem">
                            <template #iconLeft><PhPlus :size="20" /></template>
                            {{ t('tournaments.add_schedule') }}
                        </AppButton>
                    </CardSection>

                    <!-- ============================= Format -->
                    <CardSection id="section-format" :title="t('tournaments.section_format')">
                        <FormRow :label="t('tournaments.game_format')" :description="t('tournaments.game_format_hint')" required>
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.game_format"
                                    placeholder="Double-101"
                                    :error="form.errors.game_format"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.participant_count')"
                            :description="t('tournaments.participant_count_hint')"
                        >
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.participant_count"
                                    type="number"
                                    min="1"
                                    placeholder="64"
                                    :error="form.errors.participant_count"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.participant_type')"
                            :description="t('tournaments.participant_type_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <SelectField
                                    :id="id"
                                    v-model="form.participant_type"
                                    :options="toOptions(options.participantTypes)"
                                    :error="form.errors.participant_type"
                                />
                            </template>
                        </FormRow>

                        <FormRow
                            :label="t('tournaments.competition_system')"
                            :description="t('tournaments.competition_system_hint')"
                            required
                        >
                            <template #default="{ id }">
                                <AppField
                                    :id="id"
                                    v-model="form.competition_system"
                                    textarea
                                    :error="form.errors.competition_system"
                                />
                            </template>
                        </FormRow>

                        <FormRow :label="t('tournaments.scoring')" :description="t('tournaments.scoring_hint')" required>
                            <template #default="{ id }">
                                <AppField :id="id" v-model="form.scoring" textarea :error="form.errors.scoring" />
                            </template>
                        </FormRow>
                    </CardSection>

                    <!-- ============================= Regulations -->
                    <CardSection id="section-regulations" :title="t('tournaments.section_regulations')">
                        <ContextNote>{{ t('tournaments.regulations_note') }}</ContextNote>

                        <FormRow
                            :label="t('tournaments.documents')"
                            :description="t('tournaments.documents_hint', { max: options.maxDocuments })"
                            compact
                        >
                            <div class="flex flex-col gap-3">
                                <p v-if="documentOptions.length === 0" class="text-body-s text-cool-60">
                                    {{ t('tournaments.documents_empty') }}
                                </p>

                                <AppCheckbox
                                    v-for="doc in documentOptions"
                                    :key="doc.value"
                                    :model-value="form.documents.includes(doc.value)"
                                    :label="doc.category ? `${doc.label} — ${doc.category}` : doc.label"
                                    :disabled="documentLimitReached && !form.documents.includes(doc.value)"
                                    @update:model-value="toggleDocument(doc.value, $event)"
                                />

                                <p v-if="form.errors.documents" role="alert" class="text-body-xs text-danger">
                                    {{ form.errors.documents }}
                                </p>
                            </div>
                        </FormRow>
                    </CardSection>

                    <!-- Catatan `596:11483`: dua hal yang SENGAJA tidak ada di
                         layar ini, supaya tidak dicari orang. -->
                    <ContextNote tone="info">{{ t('tournaments.cms_behavior') }}</ContextNote>

                    <!-- ============================= Publish -->
                    <CardSection :title="t('news.posting_time')">
                        <FormRow
                            :label="t('news.posting_time')"
                            :description="t('news.posting_time_hint')"
                            compact
                        >
                            <div class="flex flex-col gap-4">
                                <div class="flex items-center gap-6">
                                    <AppRadio
                                        v-model="form.posting"
                                        value="now"
                                        name="tournament-posting"
                                        :label="t('news.posting_now')"
                                    />
                                    <AppRadio
                                        v-model="form.posting"
                                        value="schedule"
                                        name="tournament-posting"
                                        :label="t('news.posting_schedule')"
                                    />
                                </div>

                                <div v-if="canSchedule" class="grid w-full grid-cols-1 gap-4 sm:grid-cols-2">
                                    <AppField
                                        v-model="scheduleTime"
                                        type="time"
                                        :aria-label="t('news.schedule_time')"
                                        :error="form.errors.published_at"
                                        @update:model-value="syncPublishedAt"
                                    />
                                    <AppField
                                        v-model="scheduleDate"
                                        type="date"
                                        :aria-label="t('news.schedule_date')"
                                        @update:model-value="syncPublishedAt"
                                    />
                                </div>
                            </div>
                        </FormRow>
                    </CardSection>

                    <div class="flex flex-wrap items-center gap-2">
                        <AppButton variant="outline" :disabled="form.processing" @click="submit('draft')">
                            {{ t('common.save_draft') }}
                        </AppButton>
                        <AppButton href="/tournaments" variant="outline">{{ t('common.cancel') }}</AppButton>
                        <AppButton type="submit" :disabled="form.processing">
                            {{ t('tournaments.save') }}
                        </AppButton>
                    </div>
                </div>
            </div>
        </form>

        <UnsavedGuard :dirty="form.isDirty" />
    </AdminLayout>
</template>
