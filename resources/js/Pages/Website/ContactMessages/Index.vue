<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import EmptyState from '@/Components/EmptyState.vue'
import Pagination from '@/Components/Pagination.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    EnvelopeIcon, EnvelopeOpenIcon, MagnifyingGlassIcon,
    ArchiveBoxIcon, TrashIcon, ArrowUturnLeftIcon, InboxIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    messages: { type: Object, default: () => ({ data: [] }) },
    filters:  { type: Object, default: () => ({}) },
    tab:      { type: String, default: 'inbox' },
    counts:   { type: Object, default: () => ({ inbox: 0, unread: 0, archived: 0 }) },
})

const search = ref(props.filters.search || '')

let debounceTimer = null
watch(search, () => {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
        router.get(route('website.contact-messages.index'),
            { search: search.value || undefined, tab: props.tab },
            { preserveState: true, preserveScroll: true, replace: true })
    }, 300)
})

function setTab(t) {
    router.get(route('website.contact-messages.index'),
        { tab: t, search: search.value || undefined },
        { preserveState: false })
}

const selected = ref(new Set())
function toggleSelected(id) {
    if (selected.value.has(id)) selected.value.delete(id)
    else selected.value.add(id)
    selected.value = new Set(selected.value)
}
function selectAll() {
    if (selected.value.size === props.messages.data.length) {
        selected.value = new Set()
    } else {
        selected.value = new Set(props.messages.data.map(m => m.id))
    }
}

function bulk(action) {
    if (!selected.value.size) return
    if (action === 'delete' && !confirm(`Delete ${selected.value.size} message(s)? This cannot be undone.`)) return
    router.post(route('website.contact-messages.bulk'),
        { action, ids: [...selected.value] },
        { preserveScroll: true, onSuccess: () => { selected.value = new Set() } })
}

function fmt(d) {
    if (!d) return ''
    const dt = new Date(d)
    const now = new Date()
    const diffMs = now - dt
    const days = Math.floor(diffMs / 86400000)
    if (days === 0) return dt.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' })
    if (days === 1) return 'Yesterday'
    if (days < 7) return `${days} days ago`
    return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
    <Head title="Contact Messages" />
    <AppLayout :breadcrumbs="[{ label: 'Website' }, { label: 'Contact Messages' }]">
        <div class="space-y-5">
            <div class="flex items-start justify-between gap-4 flex-wrap">
                <div>
                    <h1 class="text-2xl font-bold">Contact Messages</h1>
                    <p class="text-sm text-base-content/60 mt-1">
                        Messages submitted through the public Contact form.
                    </p>
                </div>
            </div>

            <!-- Tabs -->
            <div role="tablist" class="flex flex-wrap gap-1.5 rounded-2xl bg-base-100 p-1.5 shadow-sm border border-base-200 w-fit">
                <button @click="setTab('inbox')"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[12.5px] font-semibold transition-all"
                    :class="tab === 'inbox' ? 'bg-primary text-primary-content shadow' : 'text-base-content/60 hover:bg-base-200'">
                    <InboxIcon class="w-4 h-4" /> Inbox
                    <span v-if="counts.inbox" class="text-[10px] opacity-75">({{ counts.inbox }})</span>
                </button>
                <button @click="setTab('unread')"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[12.5px] font-semibold transition-all"
                    :class="tab === 'unread' ? 'bg-primary text-primary-content shadow' : 'text-base-content/60 hover:bg-base-200'">
                    <EnvelopeIcon class="w-4 h-4" /> Unread
                    <span v-if="counts.unread" class="px-1.5 py-0.5 rounded-full bg-error text-white text-[9px] font-bold">{{ counts.unread }}</span>
                </button>
                <button @click="setTab('archived')"
                    class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-[12.5px] font-semibold transition-all"
                    :class="tab === 'archived' ? 'bg-primary text-primary-content shadow' : 'text-base-content/60 hover:bg-base-200'">
                    <ArchiveBoxIcon class="w-4 h-4" /> Archived
                    <span v-if="counts.archived" class="text-[10px] opacity-75">({{ counts.archived }})</span>
                </button>
            </div>

            <!-- Search + bulk actions -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/40" />
                    <input v-model="search" type="text" placeholder="Search name, email, subject, message…"
                        class="input input-bordered input-sm w-full pl-9 text-sm" />
                </div>
                <Transition>
                    <div v-if="selected.size" class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-900">
                        <span class="font-bold">{{ selected.size }} selected</span>
                        <button @click="bulk('read')" class="btn btn-ghost btn-xs gap-1">
                            <EnvelopeOpenIcon class="w-3.5 h-3.5" /> Read
                        </button>
                        <button @click="bulk('unread')" class="btn btn-ghost btn-xs gap-1">
                            <EnvelopeIcon class="w-3.5 h-3.5" /> Unread
                        </button>
                        <button v-if="tab !== 'archived'" @click="bulk('archive')" class="btn btn-ghost btn-xs gap-1">
                            <ArchiveBoxIcon class="w-3.5 h-3.5" /> Archive
                        </button>
                        <button v-else @click="bulk('unarchive')" class="btn btn-ghost btn-xs gap-1">
                            <ArrowUturnLeftIcon class="w-3.5 h-3.5" /> Restore
                        </button>
                        <button @click="bulk('delete')" class="btn btn-ghost btn-xs gap-1 text-error">
                            <TrashIcon class="w-3.5 h-3.5" /> Delete
                        </button>
                    </div>
                </Transition>
            </div>

            <EmptyState v-if="!messages.data?.length"
                :icon="InboxIcon"
                title="No messages here"
                description="When visitors submit the public Contact form, their messages will land here." />

            <template v-else>
                <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-[11px] uppercase tracking-wider text-base-content/50">
                                <th class="w-10 text-center">
                                    <input type="checkbox" class="checkbox checkbox-xs"
                                        :checked="selected.size === messages.data.length && messages.data.length > 0"
                                        @change="selectAll" />
                                </th>
                                <th class="w-10"></th>
                                <th>From</th>
                                <th>Subject &amp; Preview</th>
                                <th class="w-28">Received</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in messages.data" :key="m.id"
                                class="hover cursor-pointer"
                                :class="!m.is_read && !m.is_archived ? 'font-semibold bg-emerald-50/30' : ''"
                                @click="$inertia.visit(route('website.contact-messages.show', m.id))">
                                <td @click.stop class="text-center">
                                    <input type="checkbox" class="checkbox checkbox-xs"
                                        :checked="selected.has(m.id)" @change="toggleSelected(m.id)" />
                                </td>
                                <td>
                                    <EnvelopeIcon v-if="!m.is_read && !m.is_archived" class="w-4 h-4 text-emerald-600" />
                                    <EnvelopeOpenIcon v-else class="w-4 h-4 text-base-content/30" />
                                </td>
                                <td>
                                    <div class="text-sm truncate max-w-[140px]">{{ m.name }}</div>
                                    <div class="text-[11px] text-base-content/55 truncate max-w-[180px]">{{ m.email }}</div>
                                </td>
                                <td>
                                    <div class="text-sm truncate max-w-md">{{ m.subject || '(No subject)' }}</div>
                                    <div class="text-[11px] text-base-content/55 truncate max-w-md mt-0.5">{{ m.message }}</div>
                                </td>
                                <td class="text-xs text-base-content/60 whitespace-nowrap">{{ fmt(m.created_at) }}</td>
                                <td>
                                    <ChevronRightIcon class="w-4 h-4 text-base-content/30" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="messages.links" :from="messages.from" :to="messages.to" :total="messages.total" />
            </template>
        </div>
    </AppLayout>
</template>
