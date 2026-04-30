<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PlusIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    DocumentDuplicateIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    templates: { type: Array, default: () => [] },
})

const confirmOpen = ref(false)
const toDelete = ref(null)

function askDelete(t) {
    toDelete.value = t
    confirmOpen.value = true
}

function doDelete() {
    if (!toDelete.value) return
    router.delete(route('result-card-templates.destroy', toDelete.value.id), {
        onSuccess: () => {
            confirmOpen.value = false
            toDelete.value = null
        },
    })
}
</script>

<template>
    <Head title="Result Card Templates" />
    <AppLayout :breadcrumbs="[{ label: 'Master Data' }, { label: 'Result Card Templates' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold flex items-center gap-2">
                        <DocumentDuplicateIcon class="w-7 h-7 text-primary" />
                        Result Card Templates
                    </h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Upload custom HTML templates for printing result cards per academic session.
                    </p>
                </div>
                <Link :href="route('result-card-templates.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Upload New Template
                </Link>
            </div>

            <div v-if="templates.length" class="card bg-base-100 shadow-md">
                <div class="card-body p-0">
                    <div class="overflow-x-auto">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Academic Session</th>
                                    <th>School</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in templates" :key="t.id" class="hover">
                                    <td>
                                        <div class="font-semibold">{{ t.name }}</div>
                                        <div v-if="t.footer_text" class="text-xs text-base-content/50 truncate max-w-xs">
                                            {{ t.footer_text }}
                                        </div>
                                    </td>
                                    <td class="text-sm">
                                        {{ t.academic_session?.name || 'Any session' }}
                                    </td>
                                    <td class="text-sm">
                                        <span v-if="t.school">{{ t.school.name }}</span>
                                        <span v-else class="badge badge-ghost badge-sm">All Schools</span>
                                    </td>
                                    <td>
                                        <div class="flex gap-1 flex-wrap">
                                            <span v-if="t.is_default" class="badge badge-warning badge-sm">Default</span>
                                            <span :class="t.is_active ? 'badge badge-success badge-sm' : 'badge badge-ghost badge-sm'">
                                                {{ t.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-sm text-base-content/60">{{ t.created_by?.name || '—' }}</td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <Link :href="route('result-card-templates.show', t.id)" class="btn btn-ghost btn-sm" title="Preview">
                                                <EyeIcon class="w-4 h-4" />
                                            </Link>
                                            <Link :href="route('result-card-templates.edit', t.id)" class="btn btn-ghost btn-sm" title="Edit">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button @click="askDelete(t)" class="btn btn-ghost btn-sm text-error" title="Delete">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <EmptyState
                v-else
                title="No templates yet"
                description="Upload a custom HTML template to override the default result card design."
                action-text="Upload New Template"
                :action-href="route('result-card-templates.create')"
            />
        </div>

        <ConfirmDialog
            :show="confirmOpen"
            title="Delete Template"
            :message="`Are you sure you want to delete '${toDelete?.name}'? This action cannot be undone.`"
            type="danger"
            @confirm="doDelete"
            @cancel="confirmOpen = false"
        />
    </AppLayout>
</template>
