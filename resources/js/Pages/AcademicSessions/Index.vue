<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'
import { useDebouncedSearch } from '@/Composables/useDebouncedSearch'
import { formatDate } from '@/Utils/format'

const props = defineProps({
    sessions: Object,
    filters: Object,
})

const search = useDebouncedSearch({
    routeName: 'academic-sessions.index',
    initial: props.filters?.search || '',
    only: ['sessions', 'filters'],
    delay: 0,
})
const confirmDelete = ref(false)
const sessionToDelete = ref(null)
const confirmSwitch = ref(false)
const sessionToSwitch = ref(null)

function confirmDeleteSession(session) {
    sessionToDelete.value = session
    confirmDelete.value = true
}

function deleteSession() {
    if (sessionToDelete.value) {
        router.delete(route('academic-sessions.destroy', sessionToDelete.value.id), {
            onSuccess: () => { confirmDelete.value = false; sessionToDelete.value = null }
        })
    }
}

function confirmSwitchSession(session) {
    sessionToSwitch.value = session
    confirmSwitch.value = true
}

function switchSession() {
    if (sessionToSwitch.value) {
        router.post(route('academic-sessions.switch', sessionToSwitch.value.id), {}, {
            onSuccess: () => { confirmSwitch.value = false; sessionToSwitch.value = null }
        })
    }
}
</script>

<template>
    <Head title="Academic Sessions" />
    <AppLayout :breadcrumbs="[{ label: 'Academic Sessions' }]">
        <div class="space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight">Academic Sessions</h1>
                    <p class="text-sm text-base-content/55 mt-0.5">
                        {{ sessions?.total || 0 }} session{{ (sessions?.total || 0) === 1 ? '' : 's' }}
                        <span v-if="search">matching "{{ search }}"</span>
                    </p>
                </div>
                <Link :href="route('academic-sessions.create')" class="btn btn-primary btn-sm gap-1.5">
                    <PlusIcon class="w-4 h-4" /> Add Session
                </Link>
            </div>

            <section class="surface overflow-hidden">
                <header class="surface-header">
                    <div class="flex-1 max-w-md">
                        <SearchFilter v-model="search" placeholder="Search sessions…" />
                    </div>
                </header>

                <div class="table-sticky-wrap" style="--table-max-h: 65vh;" v-if="sessions?.data?.length">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-12">#</th>
                                <th>Session</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Current</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(session, i) in sessions.data" :key="session.id">
                                <td class="text-xs font-mono text-base-content/55 tabular-nums">{{ sessions.from + i }}</td>
                                <td class="font-bold text-sm">{{ session.name }}</td>
                                <td class="text-[13px] text-base-content/75 whitespace-nowrap tabular-nums">{{ formatDate(session.start_date) }}</td>
                                <td class="text-[13px] text-base-content/75 whitespace-nowrap tabular-nums">{{ formatDate(session.end_date) }}</td>
                                <td>
                                    <span v-if="session.is_current" class="badge badge-success badge-sm gap-1">
                                        <CheckCircleIcon class="w-3 h-3" /> Current
                                    </span>
                                    <button v-else @click="confirmSwitchSession(session)" class="btn btn-ghost btn-xs text-primary">
                                        Set Current
                                    </button>
                                </td>
                                <td>
                                    <span :class="['badge badge-sm', session.is_active ? 'badge-success' : 'badge-error']">
                                        {{ session.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex gap-0.5 justify-end">
                                        <Link :href="route('academic-sessions.edit', session.id)" class="btn btn-ghost btn-xs btn-square" title="Edit">
                                            <PencilSquareIcon class="w-4 h-4" />
                                        </Link>
                                        <button @click="confirmDeleteSession(session)" class="btn btn-ghost btn-xs btn-square text-error" title="Delete">
                                            <TrashIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <EmptyState v-if="!sessions?.data?.length"
                    title="No academic sessions found"
                    :description="search ? 'Try a different search term.' : 'Get started by adding your first academic session.'"
                    action-text="Add Session"
                    :action-href="route('academic-sessions.create')" />

                <footer v-if="sessions?.data?.length && sessions.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ sessions.from }}–{{ sessions.to }}</span>
                        of <span class="text-base-content font-bold">{{ sessions.total }}</span>
                    </span>
                    <Pagination :links="sessions.links" />
                </footer>
            </section>
        </div>

        <ConfirmDialog
            :show="confirmDelete"
            title="Delete Academic Session"
            :message="`Are you sure you want to delete ${sessionToDelete?.name}? This action cannot be undone.`"
            type="danger"
            @confirm="deleteSession"
            @cancel="confirmDelete = false"
        />

        <ConfirmDialog
            :show="confirmSwitch"
            title="Switch Current Session"
            :message="`Set ${sessionToSwitch?.name} as the current academic session? This will affect all operations across the system.`"
            type="warning"
            @confirm="switchSession"
            @cancel="confirmSwitch = false"
        />
    </AppLayout>
</template>
