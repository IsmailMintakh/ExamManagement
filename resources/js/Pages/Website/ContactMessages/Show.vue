<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import {
    ArrowLeftIcon, EnvelopeIcon, PhoneIcon, ClockIcon,
    ArchiveBoxIcon, TrashIcon, ArrowUturnLeftIcon, CheckBadgeIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'
import { confirmDelete } from '@/lib/swal'

const props = defineProps({
    message: { type: Object, required: true },
})

function archive() {
    router.put(route('website.contact-messages.update', props.message.id),
        { is_archived: true }, { preserveScroll: true })
}
function unarchive() {
    router.put(route('website.contact-messages.update', props.message.id),
        { is_archived: false }, { preserveScroll: true })
}
function markUnread() {
    router.put(route('website.contact-messages.update', props.message.id),
        { is_read: false }, { preserveScroll: true })
}
function markReplied() {
    router.put(route('website.contact-messages.update', props.message.id),
        { replied_at: new Date().toISOString() }, { preserveScroll: true })
}
async function destroy() {
    if (!await confirmDelete({ title: 'Delete this message?', text: 'This will permanently remove it.' })) return
    router.delete(route('website.contact-messages.destroy', props.message.id))
}

function fmt(d) {
    if (!d) return '—'
    return new Date(d).toLocaleString('en-GB', {
        day: '2-digit', month: 'long', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function fmtRelative(d) {
    if (!d) return ''
    const dt = new Date(d)
    const days = Math.floor((Date.now() - dt) / 86400000)
    if (days === 0) return 'today'
    if (days === 1) return 'yesterday'
    return `${days} days ago`
}
</script>

<template>
    <Head :title="`${message.name} — ${message.subject || 'Message'}`" />
    <AppLayout :breadcrumbs="[
        { label: 'Website' },
        { label: 'Contact Messages', href: route('website.contact-messages.index') },
        { label: message.subject || 'Message' },
    ]">
        <div class="max-w-3xl mx-auto space-y-6">
            <div class="flex items-center justify-between gap-4">
                <Link :href="route('website.contact-messages.index')" class="btn btn-ghost btn-sm gap-1.5">
                    <ArrowLeftIcon class="w-4 h-4" /> Back to inbox
                </Link>
                <div class="flex items-center gap-1">
                    <button v-if="!message.replied_at" @click="markReplied" class="btn btn-ghost btn-sm gap-1.5" title="Mark as replied">
                        <CheckBadgeIcon class="w-4 h-4" /> Mark Replied
                    </button>
                    <button @click="markUnread" class="btn btn-ghost btn-sm btn-square" title="Mark unread">
                        <EnvelopeIcon class="w-4 h-4" />
                    </button>
                    <button v-if="!message.is_archived" @click="archive" class="btn btn-ghost btn-sm btn-square" title="Archive">
                        <ArchiveBoxIcon class="w-4 h-4" />
                    </button>
                    <button v-else @click="unarchive" class="btn btn-ghost btn-sm btn-square" title="Restore">
                        <ArrowUturnLeftIcon class="w-4 h-4" />
                    </button>
                    <button @click="destroy" class="btn btn-ghost btn-sm btn-square text-error" title="Delete">
                        <TrashIcon class="w-4 h-4" />
                    </button>
                </div>
            </div>

            <!-- Header -->
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-emerald-700 text-white flex items-center justify-center shrink-0">
                            <UserCircleIcon class="w-7 h-7" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h1 class="text-xl font-bold">{{ message.name }}</h1>
                                <span v-if="message.replied_at" class="badge badge-success badge-sm gap-1">
                                    <CheckBadgeIcon class="w-3 h-3" /> Replied
                                </span>
                                <span v-if="message.is_archived" class="badge badge-ghost badge-sm">Archived</span>
                            </div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-base-content/65">
                                <a :href="`mailto:${message.email}`" class="inline-flex items-center gap-1.5 hover:text-emerald-700">
                                    <EnvelopeIcon class="w-3.5 h-3.5" /> {{ message.email }}
                                </a>
                                <a v-if="message.phone" :href="`tel:${message.phone}`" class="inline-flex items-center gap-1.5 hover:text-emerald-700">
                                    <PhoneIcon class="w-3.5 h-3.5" /> {{ message.phone }}
                                </a>
                                <span class="inline-flex items-center gap-1.5">
                                    <ClockIcon class="w-3.5 h-3.5" /> {{ fmt(message.created_at) }} <span class="opacity-60">· {{ fmtRelative(message.created_at) }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <h2 v-if="message.subject" class="text-base font-bold mt-6">{{ message.subject }}</h2>
                </div>
            </div>

            <!-- Body -->
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-6">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-base-content/40 mb-3">Message</div>
                    <div class="prose prose-slate max-w-none whitespace-pre-line text-base-content leading-relaxed">{{ message.message }}</div>
                </div>
            </div>

            <!-- Reply CTA -->
            <div class="card bg-emerald-50 border border-emerald-200">
                <div class="card-body p-5 flex-row items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="text-sm font-bold">Reply directly to {{ message.name }}</h3>
                        <p class="text-xs text-emerald-900/70 mt-1">
                            Opens your email client. After sending, click "Mark Replied" above to track this thread.
                        </p>
                    </div>
                    <a :href="`mailto:${message.email}?subject=${encodeURIComponent('Re: ' + (message.subject || 'Your message'))}`"
                       class="btn btn-primary btn-sm gap-2">
                        <EnvelopeIcon class="w-4 h-4" /> Open in Email
                    </a>
                </div>
            </div>

            <!-- Meta -->
            <details class="text-xs text-base-content/55">
                <summary class="font-bold cursor-pointer">Technical details</summary>
                <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-y-1.5 gap-x-4 px-4">
                    <dt class="font-semibold">IP address</dt><dd class="font-mono">{{ message.ip_address || '—' }}</dd>
                    <dt class="font-semibold">User agent</dt><dd class="font-mono text-[10px] truncate">{{ message.user_agent || '—' }}</dd>
                    <dt class="font-semibold">Read</dt><dd>{{ message.is_read ? 'Yes' : 'No' }}</dd>
                    <dt class="font-semibold">Replied at</dt><dd>{{ fmt(message.replied_at) }}</dd>
                </dl>
            </details>
        </div>
    </AppLayout>
</template>
