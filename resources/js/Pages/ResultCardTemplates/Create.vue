<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import FormInput from '@/Components/FormInput.vue'
import FormSelect from '@/Components/FormSelect.vue'
import FormTextarea from '@/Components/FormTextarea.vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    DocumentDuplicateIcon,
    ClipboardIcon,
    EyeIcon,
    PhotoIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    template: { type: Object, default: null },
    sessions: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    placeholders: { type: Array, default: () => [] },
})

const isEdit = computed(() => !!props.template)

const form = useForm({
    name: props.template?.name || '',
    academic_session_id: props.template?.academic_session_id || '',
    school_id: props.template?.school_id || '',
    html_template: props.template?.html_template || defaultTemplate(),
    footer_text: props.template?.footer_text || '',
    is_default: props.template?.is_default ?? false,
    is_active: props.template?.is_active ?? true,
    header_image: null,
    _method: isEdit.value ? 'put' : 'post',
})

function defaultTemplate() {
    return `<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1a1a1a; }
  .page { padding: 20mm; }
  .header { text-align: center; border-bottom: 2px solid #1a365d; padding-bottom: 10px; margin-bottom: 16px; }
  .school-name { font-size: 20px; font-weight: bold; color: #1a365d; text-transform: uppercase; }
  .info { margin: 12px 0; }
  .info b { display: inline-block; min-width: 130px; color: #555; }
  .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #777; }
</style>
</head>
<body>
  <div class="page">
    <div class="header">
      <div class="school-name">{{school_name}}</div>
      <div>{{school_address}}</div>
      <h3>Report Card - {{exam_name}} ({{academic_session}})</h3>
    </div>

    <div class="info">
      <p><b>Student:</b> {{student_name}}</p>
      <p><b>Father's Name:</b> {{student_father_name}}</p>
      <p><b>Admission No:</b> {{student_admission_no}}</p>
      <p><b>Roll No:</b> {{student_roll_no}}</p>
      <p><b>Class / Section:</b> {{class_name}} / {{section_name}}</p>
    </div>

    {{subjects_table}}

    <div class="info">
      <p><b>Total Marks:</b> {{total_marks}}</p>
      <p><b>Obtained Marks:</b> {{obtained_marks}}</p>
      <p><b>Percentage:</b> {{percentage}}%</p>
      <p><b>Grade:</b> {{grade}}</p>
      <p><b>Position:</b> {{position}}</p>
      <p><b>Status:</b> <strong>{{status}}</strong></p>
    </div>

    <div class="footer">
      Issued on {{date}}
    </div>
  </div>
</body>
</html>`
}

const previewHtml = ref('')
const previewLoading = ref(false)

async function loadPreview() {
    previewLoading.value = true
    try {
        const res = await window.axios.post(route('result-card-templates.preview'), {
            html_template: form.html_template,
        })
        previewHtml.value = res.data.html
    } catch (e) {
        previewHtml.value = '<p style="color:red;">Failed to render preview.</p>'
    } finally {
        previewLoading.value = false
    }
}

const copiedToken = ref(null)
function copyToken(token) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(token)
    }
    copiedToken.value = token
    setTimeout(() => { copiedToken.value = null }, 1200)
}

function onHeaderImage(e) {
    form.header_image = e.target.files[0] || null
}

function submit() {
    if (isEdit.value) {
        form.post(route('result-card-templates.update', props.template.id), {
            forceFormData: true,
        })
    } else {
        form._method = 'post'
        form.post(route('result-card-templates.store'), {
            forceFormData: true,
        })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Result Card Template' : 'Upload Result Card Template'" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Master Data' },
            { label: 'Result Card Templates', href: route('result-card-templates.index') },
            { label: isEdit ? 'Edit' : 'Create' },
        ]"
    >
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <DocumentDuplicateIcon class="w-7 h-7 text-primary" />
                <h1 class="text-2xl font-bold">
                    {{ isEdit ? 'Edit Template' : 'Upload New Template' }}
                </h1>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Form (left, 2 cols) -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body space-y-4">
                                <h3 class="text-lg font-semibold border-b border-base-200 pb-2">Template Details</h3>

                                <FormInput
                                    v-model="form.name"
                                    label="Template Name"
                                    :error="form.errors.name"
                                    required
                                    placeholder="e.g., Annual Exam 2025-2026"
                                />

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <FormSelect
                                        v-model="form.academic_session_id"
                                        label="Academic Session"
                                        :error="form.errors.academic_session_id"
                                        :options="sessions.map(s => ({ value: s.id, label: s.name }))"
                                        placeholder="Any session"
                                    />
                                    <FormSelect
                                        v-model="form.school_id"
                                        label="School (optional)"
                                        :error="form.errors.school_id"
                                        :options="schools.map(s => ({ value: s.id, label: s.name }))"
                                        placeholder="All Schools"
                                    />
                                </div>

                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-base-content/70">
                                        Header Image (Letterhead)
                                    </label>
                                    <input
                                        type="file"
                                        accept="image/*"
                                        @change="onHeaderImage"
                                        class="file-input file-input-bordered w-full"
                                    />
                                    <p v-if="form.errors.header_image" class="mt-1 text-xs text-error">
                                        {{ form.errors.header_image }}
                                    </p>
                                    <p v-else class="mt-1 text-xs text-base-content/40">
                                        Optional. Upload an image to use as the page letterhead.
                                    </p>
                                    <div v-if="template?.header_image" class="mt-2 flex items-center gap-2 text-xs text-base-content/60">
                                        <PhotoIcon class="w-4 h-4" />
                                        Current image: {{ template.header_image }}
                                    </div>
                                </div>

                                <FormTextarea
                                    v-model="form.footer_text"
                                    label="Footer Text"
                                    :rows="2"
                                    :error="form.errors.footer_text"
                                    placeholder="e.g., This is a computer generated document."
                                />

                                <div class="flex flex-wrap items-center gap-6">
                                    <label class="label cursor-pointer justify-start gap-3">
                                        <input type="checkbox" v-model="form.is_default" class="checkbox checkbox-primary" />
                                        <div>
                                            <span class="label-text font-medium">Set as Default</span>
                                            <p class="text-xs text-base-content/50">Use for all matching session/school cards.</p>
                                        </div>
                                    </label>
                                    <label class="label cursor-pointer justify-start gap-3">
                                        <input type="checkbox" v-model="form.is_active" class="checkbox checkbox-primary" />
                                        <div>
                                            <span class="label-text font-medium">Active</span>
                                            <p class="text-xs text-base-content/50">Inactive templates are not used.</p>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-base-100 shadow-md">
                            <div class="card-body space-y-3">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold">HTML Template</h3>
                                    <button type="button" @click="loadPreview" class="btn btn-sm btn-outline gap-1">
                                        <EyeIcon class="w-4 h-4" />
                                        {{ previewLoading ? 'Loading...' : 'Live Preview' }}
                                    </button>
                                </div>
                                <textarea
                                    v-model="form.html_template"
                                    rows="20"
                                    class="textarea textarea-bordered font-mono text-xs leading-relaxed"
                                    :class="{ 'textarea-error': form.errors.html_template }"
                                    placeholder="Paste your HTML template here..."
                                ></textarea>
                                <p v-if="form.errors.html_template" class="text-xs text-error">
                                    {{ form.errors.html_template }}
                                </p>
                            </div>
                        </div>

                        <div v-if="previewHtml" class="card bg-base-100 shadow-md">
                            <div class="card-body">
                                <h3 class="text-lg font-semibold">Preview (with sample data)</h3>
                                <div class="border border-base-300 rounded-lg bg-white">
                                    <iframe
                                        :srcdoc="previewHtml"
                                        class="w-full h-[700px] rounded-lg"
                                    ></iframe>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholders sidebar (right) -->
                    <aside class="space-y-4">
                        <div class="card bg-base-100 shadow-md sticky top-20">
                            <div class="card-body">
                                <h3 class="text-lg font-semibold flex items-center gap-2">
                                    <ClipboardIcon class="w-5 h-5 text-primary" />
                                    Available Placeholders
                                </h3>
                                <p class="text-xs text-base-content/60">
                                    Click to copy. Paste into the HTML template.
                                </p>
                                <ul class="space-y-1 max-h-[600px] overflow-y-auto pr-1">
                                    <li v-for="p in placeholders" :key="p.token">
                                        <button
                                            type="button"
                                            @click="copyToken(p.token)"
                                            class="w-full text-left flex flex-col gap-0.5 rounded-lg border border-base-200 hover:border-primary/40 hover:bg-primary/5 p-2 transition-colors"
                                        >
                                            <code class="text-xs font-bold text-primary">{{ p.token }}</code>
                                            <span class="text-2xs text-base-content/55">{{ p.description }}</span>
                                            <span v-if="copiedToken === p.token" class="text-2xs text-success">Copied!</span>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </aside>
                </div>

                <div class="flex justify-end gap-3">
                    <Link :href="route('result-card-templates.index')" class="btn btn-ghost">Cancel</Link>
                    <button type="submit" class="btn btn-primary" :disabled="form.processing">
                        <span v-if="form.processing" class="loading loading-spinner loading-sm"></span>
                        {{ isEdit ? 'Update Template' : 'Save Template' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
