<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PencilSquareIcon,
    TrashIcon,
    DocumentDuplicateIcon,
    ArrowLeftIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    template: { type: Object, required: true },
    renderedHtml: { type: String, default: '' },
})

const confirmOpen = ref(false)

function doDelete() {
    router.delete(route('result-card-templates.destroy', props.template.id), {
        onSuccess: () => { confirmOpen.value = false },
    })
}
</script>

<template>
    <Head :title="template.name" />
    <AppLayout
        :breadcrumbs="[
            { label: 'Master Data' },
            { label: 'Result Card Templates', href: route('result-card-templates.index') },
            { label: template.name },
        ]"
    >
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <DocumentDuplicateIcon class="w-7 h-7 text-primary" />
                        {{ template.name }}
                    </h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-base-content/60">
                        <span class="badge badge-outline">
                            Session: {{ template.academic_session?.name || 'Any' }}
                        </span>
                        <span class="badge badge-outline">
                            School: {{ template.school?.name || 'All Schools' }}
                        </span>
                        <span v-if="template.is_default" class="badge badge-warning">Default</span>
                        <span :class="template.is_active ? 'badge badge-success' : 'badge badge-ghost'">
                            {{ template.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p v-if="template.created_by" class="mt-1 text-xs text-base-content/50">
                        Created by {{ template.created_by.name }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('result-card-templates.index')" class="btn btn-ghost gap-1">
                        <ArrowLeftIcon class="w-4 h-4" /> Back
                    </Link>
                    <Link :href="route('result-card-templates.edit', template.id)" class="btn btn-outline gap-1">
                        <PencilSquareIcon class="w-4 h-4" /> Edit
                    </Link>
                    <button @click="confirmOpen = true" class="btn btn-error btn-outline gap-1">
                        <TrashIcon class="w-4 h-4" /> Delete
                    </button>
                </div>
            </div>

            <div v-if="template.footer_text" class="alert alert-info">
                <span>Footer: {{ template.footer_text }}</span>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <h3 class="text-lg font-semibold mb-2">Preview (sample data)</h3>
                    <div class="border border-base-300 rounded-lg bg-white">
                        <iframe
                            :srcdoc="renderedHtml"
                            class="w-full h-[1000px] rounded-lg"
                        ></iframe>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            :show="confirmOpen"
            title="Delete Template"
            :message="`Are you sure you want to delete '${template.name}'? This action cannot be undone.`"
            type="danger"
            @confirm="doDelete"
            @cancel="confirmOpen = false"
        />
    </AppLayout>
</template>
