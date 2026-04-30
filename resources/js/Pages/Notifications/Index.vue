<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'
import { BellIcon, CheckIcon, EnvelopeOpenIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    notifications: Object,
    unreadCount: Number,
    filters: Object,
})

function markAsRead(id) {
    router.post(route('notifications.read', id), {}, { preserveScroll: true })
}

function markAllAsRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true })
}

function formatDate(dateString) {
    if (!dateString) return ''
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / 60000)
    const diffHours = Math.floor(diffMs / 3600000)
    const diffDays = Math.floor(diffMs / 86400000)

    if (diffMins < 1) return 'Just now'
    if (diffMins < 60) return `${diffMins}m ago`
    if (diffHours < 24) return `${diffHours}h ago`
    if (diffDays < 7) return `${diffDays}d ago`
    return date.toLocaleDateString()
}
</script>

<template>
    <Head title="Notifications" />
    <AppLayout :breadcrumbs="[{ label: 'Notifications' }]">
        <div class="max-w-3xl mx-auto space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold">Notifications</h1>
                    <span v-if="unreadCount > 0" class="badge badge-primary badge-sm">{{ unreadCount }} unread</span>
                </div>
                <button @click="markAllAsRead" class="btn btn-ghost btn-sm gap-1" v-if="unreadCount > 0">
                    <CheckIcon class="w-4 h-4" /> Mark all read
                </button>
            </div>

            <div class="space-y-2" v-if="notifications?.data?.length">
                <div v-for="n in notifications.data" :key="n.id"
                    :class="['card bg-base-100 shadow-sm hover:shadow-md transition-shadow', !n.read_at ? 'border-l-4 border-primary' : 'opacity-75']">
                    <div class="card-body p-4 flex-row items-center gap-4">
                        <div :class="['p-2 rounded-full', !n.read_at ? 'bg-primary/10' : 'bg-base-200']">
                            <BellIcon :class="['w-5 h-5', !n.read_at ? 'text-primary' : 'text-base-content/40']" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p :class="['text-sm', !n.read_at ? 'font-semibold' : 'font-medium text-base-content/70']">
                                {{ n.data?.title }}
                            </p>
                            <p class="text-xs text-base-content/60 mt-0.5">{{ n.data?.message }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-base-content/40 whitespace-nowrap">{{ formatDate(n.created_at) }}</span>
                            <button v-if="!n.read_at" @click.stop="markAsRead(n.id)" class="btn btn-ghost btn-xs" title="Mark as read">
                                <EnvelopeOpenIcon class="w-4 h-4" />
                            </button>
                            <span v-else class="badge badge-ghost badge-xs">Read</span>
                        </div>
                    </div>
                </div>
                <div class="mt-4"><Pagination :links="notifications.links" /></div>
            </div>
            <EmptyState v-else title="No notifications" description="You're all caught up! No new notifications." />
        </div>
    </AppLayout>
</template>
