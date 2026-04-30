<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import SearchFilter from '@/Components/SearchFilter.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { PlusIcon, PencilSquareIcon, TrashIcon, CheckCircleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    sessions: Object,
    filters: Object,
})

const search = ref(props.filters?.search || '')
const confirmDelete = ref(false)
const sessionToDelete = ref(null)
const confirmSwitch = ref(false)
const sessionToSwitch = ref(null)

function handleSearch(val) {
    router.get(route('academic-sessions.index'), { search: val }, { preserveState: true, replace: true })
}

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
        <div class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 class="text-2xl font-bold">Academic Sessions</h1>
                <Link :href="route('academic-sessions.create')" class="btn btn-primary gap-2">
                    <PlusIcon class="w-5 h-5" /> Add Session
                </Link>
            </div>

            <div class="card bg-base-100 shadow-md">
                <div class="card-body">
                    <SearchFilter v-model="search" @update:model-value="handleSearch" />

                    <div class="overflow-x-auto mt-4" v-if="sessions?.data?.length">
                        <table class="table table-zebra">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Current</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(session, i) in sessions.data" :key="session.id">
                                    <td>{{ sessions.from + i }}</td>
                                    <td class="font-medium">{{ session.name }}</td>
                                    <td class="text-sm">{{ session.start_date }}</td>
                                    <td class="text-sm">{{ session.end_date }}</td>
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
                                    <td>
                                        <div class="flex gap-1">
                                            <Link :href="route('academic-sessions.edit', session.id)" class="btn btn-ghost btn-xs">
                                                <PencilSquareIcon class="w-4 h-4" />
                                            </Link>
                                            <button @click="confirmDeleteSession(session)" class="btn btn-ghost btn-xs text-error">
                                                <TrashIcon class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div class="mt-4">
                            <Pagination :links="sessions.links" />
                        </div>
                    </div>
                    <EmptyState v-else title="No academic sessions found" description="Get started by adding your first academic session." action-text="Add Session" :action-href="route('academic-sessions.create')" />
                </div>
            </div>
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
