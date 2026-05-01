<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import FileUpload from '@/Components/FileUpload.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { ArrowLeftIcon, PhotoIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    slide: { type: Object, default: null },
})

const isEdit = computed(() => !!props.slide?.id)

const form = useForm({
    eyebrow: props.slide?.eyebrow || '',
    title: props.slide?.title || '',
    subtitle: props.slide?.subtitle || '',
    description: props.slide?.description || '',
    image: null,
    cta_label: props.slide?.cta_label || '',
    cta_url: props.slide?.cta_url || '',
    cta_secondary_label: props.slide?.cta_secondary_label || '',
    cta_secondary_url: props.slide?.cta_secondary_url || '',
    overlay_color: props.slide?.overlay_color || '#0f172a',
    overlay_opacity: props.slide?.overlay_opacity ?? 60,
    sort_order: props.slide?.sort_order ?? null,
    is_active: props.slide?.is_active ?? true,
    _method: isEdit.value ? 'put' : 'post',
})

function save() {
    const url = isEdit.value
        ? route('website.hero-slides.update', props.slide.id)
        : route('website.hero-slides.store')
    form.post(url, { forceFormData: true })
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Slide' : 'New Slide'" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'Hero Slider', href: route('website.hero-slides.index') },
        { label: isEdit ? 'Edit Slide' : 'New Slide' },
    ]">
        <div class="max-w-4xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('website.hero-slides.index')" class="btn btn-ghost btn-sm btn-square">
                        <ArrowLeftIcon class="w-4 h-4" />
                    </Link>
                    <h1 class="text-2xl font-bold">{{ isEdit ? 'Edit Slide' : 'New Slide' }}</h1>
                </div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" v-model="form.is_active" class="toggle toggle-success toggle-sm" />
                    <span class="text-sm font-semibold">{{ form.is_active ? 'Active' : 'Hidden' }}</span>
                </label>
            </div>

            <form @submit.prevent="save" class="space-y-6">
                <!-- Live preview -->
                <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="relative h-64 sm:h-80 overflow-hidden bg-slate-900">
                            <img v-if="slide?.image_url && !form.image"
                                :src="slide.image_url" class="absolute inset-0 w-full h-full object-cover" />
                            <div v-else-if="!form.image" class="absolute inset-0 flex items-center justify-center">
                                <PhotoIcon class="w-16 h-16 text-white/20" />
                            </div>
                            <div class="absolute inset-0"
                                :style="`background-color: ${form.overlay_color}; opacity: ${form.overlay_opacity / 100}`"></div>
                            <div class="relative h-full flex items-center px-8 sm:px-12">
                                <div class="text-white max-w-2xl">
                                    <p v-if="form.eyebrow" class="text-[10px] uppercase tracking-[0.2em] text-amber-300 font-bold mb-2">
                                        {{ form.eyebrow }}
                                    </p>
                                    <h2 class="text-2xl sm:text-4xl font-black leading-tight">
                                        {{ form.title || 'Slide title' }}
                                    </h2>
                                    <p v-if="form.subtitle" class="mt-2 text-sm sm:text-lg text-white/85 font-light">
                                        {{ form.subtitle }}
                                    </p>
                                    <p v-if="form.description" class="mt-3 text-xs sm:text-sm text-white/70 leading-relaxed">
                                        {{ form.description }}
                                    </p>
                                    <div class="mt-5 flex flex-wrap gap-2">
                                        <span v-if="form.cta_label"
                                            class="inline-flex items-center gap-1 bg-amber-400 text-slate-900 px-4 py-2 rounded-full text-xs font-bold">
                                            {{ form.cta_label }}
                                        </span>
                                        <span v-if="form.cta_secondary_label"
                                            class="inline-flex items-center gap-1 bg-white/10 border border-white/30 text-white px-4 py-2 rounded-full text-xs font-medium">
                                            {{ form.cta_secondary_label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="px-4 py-2 bg-base-200/50 text-center">
                            <span class="text-[10px] uppercase tracking-widest font-bold text-base-content/50">Live Preview</span>
                        </div>
                    </div>
                </div>

                <!-- Content -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Slide Content</h2>
                        <FormInput v-model="form.eyebrow" label="Eyebrow (optional)"
                            placeholder="Est. 1954 · Skardu, Gilgit-Baltistan" :error="form.errors.eyebrow"
                            help-text="Small uppercase tag shown above the title." />
                        <FormInput v-model="form.title" label="Title" required
                            placeholder="Where Mountains Meet Excellence" :error="form.errors.title" />
                        <FormInput v-model="form.subtitle" label="Subtitle (optional)"
                            placeholder="Shaping young men of character for over 72 years" :error="form.errors.subtitle" />
                        <FormTextarea v-model="form.description" label="Description (optional)" rows="3"
                            placeholder="Longer supporting text..." :error="form.errors.description" />
                    </div>
                </section>

                <!-- Image -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Background Image</h2>
                        <p class="text-xs text-base-content/55 -mt-2">
                            Recommended: 1920×900px or larger. The overlay below will tint it for legibility.
                        </p>
                        <FileUpload v-model="form.image" accept="image/jpeg,image/png,image/webp"
                            :max-size="4" :preview="true" :error="form.errors.image" />
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
                                    Overlay Color
                                </label>
                                <div class="flex gap-2 items-center">
                                    <input type="color" v-model="form.overlay_color"
                                        class="h-10 w-14 rounded cursor-pointer border border-base-300" />
                                    <input type="text" v-model="form.overlay_color"
                                        class="input input-bordered text-sm flex-1" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 flex items-center gap-1 text-[12px] font-semibold text-base-content/75">
                                    Overlay Opacity ({{ form.overlay_opacity }}%)
                                </label>
                                <input type="range" min="0" max="100" v-model.number="form.overlay_opacity"
                                    class="range range-primary range-sm" />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- CTAs -->
                <section class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body space-y-5">
                        <h2 class="text-base font-bold">Call-to-Action Buttons</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <FormInput v-model="form.cta_label" label="Primary Button Text"
                                placeholder="Begin Your Application" :error="form.errors.cta_label" />
                            <FormInput v-model="form.cta_url" label="Primary Button URL"
                                placeholder="/admissions" :error="form.errors.cta_url" />
                            <FormInput v-model="form.cta_secondary_label" label="Secondary Button Text"
                                placeholder="Our Story" :error="form.errors.cta_secondary_label" />
                            <FormInput v-model="form.cta_secondary_url" label="Secondary Button URL"
                                placeholder="/about" :error="form.errors.cta_secondary_url" />
                        </div>
                    </div>
                </section>

                <!-- Save -->
                <div class="flex items-center justify-end gap-2">
                    <Link :href="route('website.hero-slides.index')" class="btn btn-ghost btn-sm">Cancel</Link>
                    <button type="submit" class="btn btn-primary btn-sm" :class="{ loading: form.processing }" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : (isEdit ? 'Update Slide' : 'Create Slide') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
