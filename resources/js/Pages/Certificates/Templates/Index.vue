<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import {
    PlusIcon, PencilIcon, TrashIcon, DocumentDuplicateIcon,
    CheckCircleIcon,
} from '@heroicons/vue/24/outline'

defineProps({ templates: Array })

const confirmDelete = ref(false)
const toDelete = ref(null)
function askDelete(t) { toDelete.value = t; confirmDelete.value = true }
function doDelete() {
    if (toDelete.value) {
        router.delete(route('certificates.templates.delete', toDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false }
        })
    }
}

const typeLabel = {
    merit: 'Merit',
    subject_topper: 'Subject Topper',
    pass: 'Pass',
    special_achievement: 'Special',
    participation: 'Participation',
    custom: 'Custom',
}
</script>

<template>
    <Head title="Certificate Templates" />
    <AppLayout :breadcrumbs="[{ label: 'Certificates', href: route('certificates.index') }, { label: 'Templates' }]">
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">Certificate Templates</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Design templates for different certificate types.
                    </p>
                </div>
                <Link :href="route('certificates.templates.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> New Template
                </Link>
            </div>

            <div v-if="templates.length === 0" class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <EmptyState
                        title="No templates yet"
                        description="Create your first template to start generating certificates."
                        :icon="DocumentDuplicateIcon"
                    />
                </div>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="t in templates" :key="t.id"
                     class="card bg-base-100 shadow-md hover:shadow-lg transition">
                    <div class="h-32 flex items-center justify-center relative overflow-hidden"
                         :style="{ background: `linear-gradient(135deg, ${t.primary_color}, ${t.accent_color})` }">
                        <div class="text-white font-serif text-xl font-bold tracking-wide text-center px-4 drop-shadow">
                            {{ t.title_text }}
                        </div>
                        <div v-if="t.is_default" class="absolute top-2 right-2 badge badge-warning gap-1 text-xs">
                            <CheckCircleIcon class="w-3 h-3" /> Default
                        </div>
                        <div class="absolute bottom-2 left-2 badge badge-neutral badge-sm capitalize">
                            {{ t.design_layout || 'classic' }}
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-semibold">{{ t.name }}</div>
                                <div class="text-xs text-base-content/60 mt-1">
                                    {{ typeLabel[t.type] }} · {{ t.orientation }}
                                </div>
                            </div>
                            <div v-if="!t.is_active" class="badge badge-ghost badge-sm">Inactive</div>
                        </div>
                        <div class="card-actions justify-end mt-3">
                            <Link :href="route('certificates.templates.edit', t.id)"
                                  class="btn btn-ghost btn-sm">
                                <PencilIcon class="w-4 h-4" />
                            </Link>
                            <button @click="askDelete(t)" class="btn btn-ghost btn-sm text-error">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model="confirmDelete"
            title="Delete Template?"
            :message="`This will delete template '${toDelete?.name}'. Certificates already issued will keep working.`"
            confirm-text="Delete"
            confirm-class="btn-error"
            @confirm="doDelete"
        />
    </AppLayout>
</template>
