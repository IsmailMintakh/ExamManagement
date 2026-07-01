<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { EyeIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    template: Object,
})

const isEdit = !!props.template?.id

const form = useForm({
    name: props.template?.name || '',
    type: props.template?.type || 'merit',
    title_text: props.template?.title_text || 'CERTIFICATE',
    body_text: props.template?.body_text || 'For outstanding academic performance in {exam_name} with {percentage}% marks.',
    primary_color: props.template?.primary_color || '#1e3a8a',
    accent_color: props.template?.accent_color || '#f59e0b',
    orientation: props.template?.orientation || 'landscape',
    border_style: props.template?.border_style || 'modern',
    design_layout: props.template?.design_layout || 'modern',
    is_default: props.template?.is_default || false,
    is_active: props.template?.is_active ?? true,
})

const designLayouts = [
    {
        value: 'modern',
        label: 'Modern Corporate',
        desc: 'Blue & gold geometric ribbons',
        preview: 'linear-gradient(135deg, #1e3a8a 0%, #1e3a8a 30%, #f59e0b 30%, #f59e0b 50%, #fff 50%)',
    },
    {
        value: 'gold',
        label: 'Gold Honors',
        desc: 'Orange title with gold medal',
        preview: 'linear-gradient(135deg, #1e293b 0%, #f97316 15%, #fff 40%, #fff 80%, #f97316 100%)',
    },
    {
        value: 'graduation',
        label: 'Graduation',
        desc: 'Navy corners with cap & medal',
        preview: 'linear-gradient(135deg, #1e3a8a 15%, #fff 15%, #fff 85%, #1e3a8a 85%)',
    },
    {
        value: 'classic',
        label: 'Classic Elegant',
        desc: 'Traditional double border',
        preview: 'repeating-linear-gradient(45deg, #c9a227, #c9a227 2px, #fff 2px, #fff 12px)',
    },
]

const types = [
    { value: 'merit', label: 'Merit' },
    { value: 'subject_topper', label: 'Subject Topper' },
    { value: 'pass', label: 'Pass' },
    { value: 'special_achievement', label: 'Special Achievement' },
    { value: 'participation', label: 'Participation' },
    { value: 'custom', label: 'Custom' },
]

const borderStyles = [
    { value: 'classic', label: 'Classic' },
    { value: 'modern', label: 'Modern' },
    { value: 'royal', label: 'Royal' },
    { value: 'minimal', label: 'Minimal' },
]

const availablePlaceholders = [
    '{student_name}', '{rank}', '{percentage}', '{grade}',
    '{exam_name}', '{academic_session}', '{class_name}',
    '{section_name}', '{subject_name}', '{school_name}',
]

function insertPlaceholder(p) {
    form.body_text = (form.body_text || '') + ' ' + p
}

// Live preview
const previewHtml = ref('')
const previewLoading = ref(false)
let previewTimer = null

function loadPreview() {
    clearTimeout(previewTimer)
    previewTimer = setTimeout(async () => {
        previewLoading.value = true
        try {
            const res = await window.axios.post(route('certificates.templates.preview'), {
                title_text: form.title_text,
                body_text: form.body_text,
                primary_color: form.primary_color,
                accent_color: form.accent_color,
                orientation: form.orientation,
                border_style: form.border_style,
                design_layout: form.design_layout,
                type: form.type,
            })
            previewHtml.value = res.data.html
        } catch (e) {
            previewHtml.value = '<p class="p-4 text-error">Preview didn\'t render — retry.</p>'
        } finally {
            previewLoading.value = false
        }
    }, 400)
}

watch([
    () => form.title_text,
    () => form.body_text,
    () => form.primary_color,
    () => form.accent_color,
    () => form.orientation,
    () => form.border_style,
    () => form.design_layout,
    () => form.type,
], loadPreview, { immediate: true })

function submit() {
    if (isEdit) {
        form.put(route('certificates.templates.update', props.template.id))
    } else {
        form.post(route('certificates.templates.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Template' : 'New Template'" />
    <AppLayout :breadcrumbs="[
        { label: 'Certificates', href: route('certificates.index') },
        { label: 'Templates', href: route('certificates.templates') },
        { label: isEdit ? 'Edit' : 'New' },
    ]">
        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Editor panel -->
            <div class="space-y-4">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body space-y-3">
                        <h2 class="card-title">Template Details</h2>

                        <div>
                            <label class="label"><span class="label-text font-semibold">Design Layout *</span></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label v-for="d in designLayouts" :key="d.value"
                                    class="cursor-pointer rounded-lg border-2 p-2 transition-all"
                                    :class="form.design_layout === d.value
                                        ? 'border-primary bg-primary/5 shadow-md'
                                        : 'border-base-200 hover:border-primary/40'">
                                    <input type="radio" v-model="form.design_layout" :value="d.value" class="hidden" />
                                    <div class="w-full h-14 rounded mb-1.5" :style="{ background: d.preview }"></div>
                                    <div class="text-xs font-bold">{{ d.label }}</div>
                                    <div class="text-[10px] text-base-content/55">{{ d.desc }}</div>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Template Name *</span></label>
                            <input v-model="form.name" type="text" required
                                   class="input input-bordered w-full" />
                            <div v-if="form.errors.name" class="text-error text-sm mt-1">{{ form.errors.name }}</div>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Type *</span></label>
                            <select v-model="form.type" class="select select-bordered w-full">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Title Text *</span></label>
                            <input v-model="form.title_text" type="text" required
                                   class="input input-bordered w-full"
                                   placeholder="Certificate of Achievement" />
                        </div>

                        <div>
                            <label class="label"><span class="label-text">Body Text *</span></label>
                            <textarea v-model="form.body_text" required rows="4"
                                      class="textarea textarea-bordered w-full"
                                      placeholder="Citation text with {placeholders}..."></textarea>
                            <div class="flex flex-wrap gap-1 mt-2">
                                <button type="button" v-for="p in availablePlaceholders" :key="p"
                                        @click="insertPlaceholder(p)"
                                        class="badge badge-outline badge-sm cursor-pointer hover:badge-primary">
                                    {{ p }}
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label"><span class="label-text">Primary Color</span></label>
                                <div class="flex gap-2">
                                    <input v-model="form.primary_color" type="color" class="w-14 h-10 rounded cursor-pointer" />
                                    <input v-model="form.primary_color" type="text" class="input input-bordered input-sm flex-1" />
                                </div>
                            </div>
                            <div>
                                <label class="label"><span class="label-text">Accent Color</span></label>
                                <div class="flex gap-2">
                                    <input v-model="form.accent_color" type="color" class="w-14 h-10 rounded cursor-pointer" />
                                    <input v-model="form.accent_color" type="text" class="input input-bordered input-sm flex-1" />
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="label"><span class="label-text">Orientation</span></label>
                                <select v-model="form.orientation" class="select select-bordered w-full">
                                    <option value="landscape">Landscape</option>
                                    <option value="portrait">Portrait</option>
                                </select>
                            </div>
                            <div>
                                <label class="label"><span class="label-text">Border Style</span></label>
                                <select v-model="form.border_style" class="select select-bordered w-full">
                                    <option v-for="b in borderStyles" :key="b.value" :value="b.value">{{ b.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex gap-4">
                            <label class="label cursor-pointer gap-2">
                                <input v-model="form.is_default" type="checkbox" class="checkbox checkbox-sm" />
                                <span class="label-text">Set as default for this type</span>
                            </label>
                            <label class="label cursor-pointer gap-2">
                                <input v-model="form.is_active" type="checkbox" class="checkbox checkbox-sm" />
                                <span class="label-text">Active</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Link :href="route('certificates.templates')" class="btn btn-ghost">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="btn btn-primary gap-2">
                        <CheckIcon class="w-5 h-5" />
                        {{ isEdit ? 'Update Template' : 'Create Template' }}
                    </button>
                </div>
            </div>

            <!-- Preview panel -->
            <div class="lg:sticky lg:top-4 h-fit">
                <div class="card bg-base-100 shadow-md">
                    <div class="card-body">
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="card-title">
                                <EyeIcon class="w-5 h-5" />
                                Live Preview <span class="text-xs font-normal text-base-content/60 ml-1">({{ form.orientation }})</span>
                            </h2>
                            <span v-if="previewLoading" class="loading loading-spinner loading-sm"></span>
                        </div>
                        <div class="border border-base-300 rounded bg-base-200 overflow-hidden flex items-center justify-center p-3"
                             :style="form.orientation === 'landscape'
                                 ? 'aspect-ratio: 297/210;'
                                 : 'aspect-ratio: 210/297;'">
                            <iframe :srcdoc="previewHtml"
                                    class="w-full h-full bg-white shadow-lg"
                                    style="border: 0; pointer-events: none;"></iframe>
                        </div>
                        <p class="text-xs text-base-content/60 mt-2">
                            Preview uses sample data. Actual certificates show real student info, plus school logo, signature &amp; stamp from Settings.
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
