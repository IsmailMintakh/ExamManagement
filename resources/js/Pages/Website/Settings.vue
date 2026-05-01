<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    BuildingLibraryIcon, PhoneIcon, ChartBarIcon, ShareIcon,
    UserCircleIcon, PhotoIcon, EyeIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    settings: { type: Object, default: () => ({}) },
})

const tabs = [
    { key: 'identity', label: 'Identity',  icon: BuildingLibraryIcon },
    { key: 'contact',  label: 'Contact',   icon: PhoneIcon },
    { key: 'stats',    label: 'Stats',     icon: ChartBarIcon },
    { key: 'social',   label: 'Social',    icon: ShareIcon },
    { key: 'ddo',      label: 'DDO Block', icon: UserCircleIcon },
    { key: 'logo',     label: 'Logo',      icon: PhotoIcon },
]

const activeTab = ref('identity')

const v = (k, d = '') => props.settings[k] ?? d

const form = useForm({
    // identity
    school_name: v('school_name'),
    school_short_name: v('school_short_name'),
    tagline: v('tagline'),
    established_year: v('established_year'),
    announcement_message: v('announcement_message'),
    footer_description: v('footer_description'),
    // contact
    phone_primary: v('phone_primary'),
    phone_secondary: v('phone_secondary'),
    email_primary: v('email_primary'),
    email_admissions: v('email_admissions'),
    address: v('address'),
    office_hours: v('office_hours'),
    google_maps_url: v('google_maps_url'),
    // stats
    stat_students_override: v('stat_students_override'),
    stat_teachers_override: v('stat_teachers_override'),
    stat_pass_rate: v('stat_pass_rate'),
    stat_years_legacy: v('stat_years_legacy'),
    // social
    social_facebook: v('social_facebook'),
    social_youtube: v('social_youtube'),
    social_instagram: v('social_instagram'),
    // ddo
    ddo_name: v('ddo_name'),
    ddo_title: v('ddo_title'),
    ddo_message: v('ddo_message'),
    ddo_serving_since: v('ddo_serving_since'),
    // logo
    logo: null,
})

const logoUrl = computed(() => {
    const path = props.settings.logo_path
    if (!path) return null
    return path.startsWith('http') ? path : `/storage/${path}`
})

function save() {
    form.post(route('website.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { form.logo = null },
    })
}
</script>

<template>
    <Head title="Website Settings" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Site Settings' }]">
        <div class="max-w-5xl mx-auto space-y-6">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">Site Settings</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Content shown across the public website. Changes apply instantly.
                    </p>
                </div>
                <Link href="/" target="_blank"
                    class="btn btn-sm btn-ghost gap-2">
                    <EyeIcon class="w-4 h-4" />
                    Preview Site
                </Link>
            </div>

            <!-- Tab pills -->
            <div role="tablist" class="flex flex-wrap gap-1.5 rounded-2xl bg-base-100 p-1.5 shadow-sm border border-base-200">
                <button
                    v-for="t in tabs" :key="t.key"
                    @click="activeTab = t.key"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[12.5px] font-semibold transition-all"
                    :class="activeTab === t.key
                        ? 'bg-primary text-primary-content shadow'
                        : 'text-base-content/60 hover:bg-base-200'"
                >
                    <component :is="t.icon" class="w-4 h-4" />
                    {{ t.label }}
                </button>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <!-- Identity -->
                <section v-show="activeTab === 'identity'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">School Identity</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.school_name" label="School Name (Full)"
                                placeholder="Government Boys Higher Secondary School No.1, Skardu" :error="form.errors.school_name" />
                            <FormInput v-model="form.school_short_name" label="Short Name"
                                placeholder="GBHSS No.1 Skardu" :error="form.errors.school_short_name" />
                            <FormInput v-model="form.tagline" label="Tagline"
                                placeholder="Where Mountains Meet Excellence" :error="form.errors.tagline" />
                            <FormInput v-model="form.established_year" label="Established Year"
                                placeholder="1954" :error="form.errors.established_year" />
                        </div>
                        <FormInput v-model="form.announcement_message" label="Header Announcement Message"
                            placeholder="Admissions Open 2026–27"
                            help-text="Shown in the green-dot strip at the top of every public page."
                            :error="form.errors.announcement_message" />
                        <FormTextarea v-model="form.footer_description" label="Footer Description" rows="3"
                            placeholder="Nestled in the heart of Baltistan, our institution has shaped generations of leaders…"
                            help-text="Longer-form blurb shown in the public site footer."
                            :error="form.errors.footer_description" />
                    </div>
                </section>

                <!-- Contact -->
                <section v-show="activeTab === 'contact'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Contact Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.phone_primary" label="Primary Phone" placeholder="+92 5815 920000" :error="form.errors.phone_primary" />
                            <FormInput v-model="form.phone_secondary" label="Secondary Phone" placeholder="+92 300 0000000" :error="form.errors.phone_secondary" />
                            <FormInput v-model="form.email_primary" type="email" label="Primary Email" placeholder="info@gbhss-skardu.edu.pk" :error="form.errors.email_primary" />
                            <FormInput v-model="form.email_admissions" type="email" label="Admissions Email" placeholder="admissions@gbhss-skardu.edu.pk" :error="form.errors.email_admissions" />
                            <FormInput v-model="form.office_hours" label="Office Hours" placeholder="Mon–Sat · 9:00 AM – 2:00 PM" :error="form.errors.office_hours" />
                            <FormInput v-model="form.google_maps_url" label="Google Maps URL" placeholder="https://maps.google.com/..." :error="form.errors.google_maps_url" />
                        </div>
                        <FormTextarea v-model="form.address" label="Address" rows="2"
                            placeholder="Hospital Road, Skardu, Gilgit-Baltistan, Pakistan" :error="form.errors.address" />
                    </div>
                </section>

                <!-- Stats -->
                <section v-show="activeTab === 'stats'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <div>
                            <h2 class="text-base font-bold">Homepage Stats</h2>
                            <p class="text-xs text-base-content/55 mt-1">
                                Leave students & teachers blank to use live database counts.
                                Pass rate &amp; legacy years are display-only.
                            </p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.stat_students_override" type="number" label="Students Count Override"
                                placeholder="(blank = live count)" help-text="Override the live student count if you want a static figure."
                                :error="form.errors.stat_students_override" />
                            <FormInput v-model="form.stat_teachers_override" type="number" label="Teachers Count Override"
                                placeholder="(blank = live count)" help-text="Override the live teacher count."
                                :error="form.errors.stat_teachers_override" />
                            <FormInput v-model="form.stat_pass_rate" type="number" label="Board Pass Rate (%)"
                                placeholder="94.2" :error="form.errors.stat_pass_rate" />
                            <FormInput v-model="form.stat_years_legacy" type="number" label="Years of Legacy"
                                placeholder="72" :error="form.errors.stat_years_legacy" />
                        </div>
                    </div>
                </section>

                <!-- Social -->
                <section v-show="activeTab === 'social'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Social Media Links</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.social_facebook" label="Facebook URL" placeholder="https://facebook.com/..." :error="form.errors.social_facebook" />
                            <FormInput v-model="form.social_youtube" label="YouTube URL" placeholder="https://youtube.com/@..." :error="form.errors.social_youtube" />
                            <FormInput v-model="form.social_instagram" label="Instagram URL" placeholder="https://instagram.com/..." :error="form.errors.social_instagram" />
                        </div>
                    </div>
                </section>

                <!-- DDO -->
                <section v-show="activeTab === 'ddo'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">DDO Message Block</h2>
                        <p class="text-xs text-base-content/55 -mt-2">
                            Shown in the "A Message From" section on the homepage.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.ddo_name" label="DDO Name" placeholder="Wazir Zamin Ali" :error="form.errors.ddo_name" />
                            <FormInput v-model="form.ddo_title" label="DDO Title" placeholder="District Drawing Officer · Skardu District" :error="form.errors.ddo_title" />
                            <FormInput v-model="form.ddo_serving_since" label="Serving Since" placeholder="2019 · 7 years" :error="form.errors.ddo_serving_since" />
                        </div>
                        <FormTextarea v-model="form.ddo_message" label="DDO Message" rows="6"
                            placeholder="As District Drawing Officer for Skardu, it is my duty and honor to ensure..."
                            :error="form.errors.ddo_message" />
                    </div>
                </section>

                <!-- Logo -->
                <section v-show="activeTab === 'logo'" class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">School Logo</h2>
                        <div v-if="logoUrl" class="flex items-center gap-4 p-4 rounded-xl bg-base-200/40">
                            <img :src="logoUrl" alt="Current logo" class="h-20 w-20 object-contain rounded-lg bg-white p-2" />
                            <div class="text-xs text-base-content/60">
                                <div class="font-semibold text-base-content">Current logo</div>
                                <div class="mt-1">Upload a new file below to replace.</div>
                            </div>
                        </div>
                        <FileUpload v-model="form.logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                            :max-size="2" :preview="true" :error="form.errors.logo" />
                    </div>
                </section>

                <!-- Sticky save bar -->
                <div class="sticky bottom-4 z-10">
                    <div class="card bg-base-100 shadow-lifted border border-base-200">
                        <div class="card-body py-3 flex-row items-center justify-between">
                            <p class="text-xs text-base-content/55">
                                Changes apply to the public website immediately.
                            </p>
                            <div class="flex items-center gap-2">
                                <span v-if="form.recentlySuccessful" class="text-xs text-success font-semibold">Saved</span>
                                <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: form.processing }" :disabled="form.processing">
                                    {{ form.processing ? 'Saving…' : 'Save Settings' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
