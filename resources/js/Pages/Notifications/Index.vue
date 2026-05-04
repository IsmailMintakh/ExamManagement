<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, router } from '@inertiajs/vue3'
import { BellIcon, CheckIcon, EnvelopeOpenIcon } from '@heroicons/vue/24/outline'
import { formatRelative } from '@/Utils/format'

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
</script>

<template>
    <Head title="Notifications" />
    <AppLayout :breadcrumbs="[{ label: 'Notifications' }]">
        <div class="max-w-3xl mx-auto space-y-5">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
                        Notifications
                        <span v-if="unreadCount > 0" class="badge badge-primary badge-sm tabular-nums">{{ unreadCount }} unread</span>
                    </h1>
                    <p class="text-sm text-base-content/55 mt-0.5">{{ notifications?.total || 0 }} total</p>
                </div>
                <button @click="markAllAsRead" class="btn btn-ghost btn-sm gap-1.5" v-if="unreadCount > 0">
                    <CheckIcon class="w-4 h-4" /> Mark all read
                </button>
            </div>

            <section class="surface overflow-hidden" v-if="notifications?.data?.length">
                <ul class="divide-y divide-base-200">
                    <li v-for="n in notifications.data" :key="n.id"
                        :class="['p-4 flex items-center gap-4 transition-colors', !n.read_at ? 'bg-primary/5' : '']">
                        <div :class="['p-2 rounded-full flex-shrink-0', !n.read_at ? 'bg-primary/15 text-primary' : 'bg-base-200 text-base-content/40']">
                            <BellIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p :class="['text-sm', !n.read_at ? 'font-bold' : 'font-medium text-base-content/70']">
                                {{ n.data?.title }}
                            </p>
                            <p class="text-xs text-base-content/60 mt-0.5">{{ n.data?.message }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span class="text-xs text-base-content/45 whitespace-nowrap tabular-nums">{{ formatRelative(n.created_at) }}</span>
                            <button v-if="!n.read_at" @click.stop="markAsRead(n.id)" class="btn btn-ghost btn-xs btn-square" title="Mark as read">
                                <EnvelopeOpenIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </li>
                </ul>
                <footer v-if="notifications.last_page > 1" class="surface-footer">
                    <span class="text-xs text-base-content/55 font-medium">
                        Showing <span class="text-base-content font-bold">{{ notifications.from }}–{{ notifications.to }}</span>
                        of <span class="text-base-content font-bold">{{ notifications.total }}</span>
                    </span>
                    <Pagination :links="notifications.links" />
                </footer>
            </section>
            <EmptyState v-else title="No notifications" description="You're all caught up! No new notifications." />
        </div>
    </AppLayout>
</template>
