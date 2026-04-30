<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import {
    XMarkIcon, BellIcon, BellAlertIcon, CheckCircleIcon, CheckBadgeIcon,
    ChartBarIcon, ClipboardDocumentListIcon, DocumentTextIcon,
    ExclamationTriangleIcon, InformationCircleIcon, EyeIcon,
    ArrowRightIcon, InboxIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    open: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'update:unreadCount'])

const groups = ref({ today: [], yesterday: [], this_week: [], older: [] })
const unreadCount = ref(0)
const loading = ref(false)
const errorMsg = ref(null)

async function fetchRecent() {
    loading.value = true
    errorMsg.value = null
    try {
        const res = await window.axios.get(route('notifications.recent'))
        groups.value = res.data?.groups || { today: [], yesterday: [], this_week: [], older: [] }
        unreadCount.value = res.data?.unread_count ?? 0
        emit('update:unreadCount', unreadCount.value)
    } catch (e) {
        errorMsg.value = 'Could not load notifications.'
    } finally {
        loading.value = false
    }
}

watch(() => props.open, (open) => { if (open) fetchRecent() })

// Esc closes, click outside closes
function onKeydown(e) {
    if (e.key === 'Escape' && props.open) emit('close')
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

const totalShown = computed(() =>
    groups.value.today.length + groups.value.yesterday.length + groups.value.this_week.length + groups.value.older.length
)

// Type → icon + color
const typeMeta = {
    marks_submitted: { icon: ClipboardDocumentListIcon, color: 'sky' },
    marks_verified:  { icon: CheckCircleIcon,         color: 'emerald' },
    result_generated:{ icon: ChartBarIcon,            color: 'violet' },
    result_finalized:{ icon: CheckBadgeIcon,          color: 'emerald' },
    exam_published:  { icon: BellAlertIcon,           color: 'amber' },
    report_ready:    { icon: DocumentTextIcon,        color: 'sky' },
    transfer:        { icon: InformationCircleIcon,   color: 'violet' },
    warning:         { icon: ExclamationTriangleIcon, color: 'amber' },
    error:           { icon: ExclamationTriangleIcon, color: 'rose' },
    general:         { icon: BellIcon,                color: 'slate' },
}

function meta(n) {
    return typeMeta[n.type] || typeMeta[n.severity] || typeMeta.general
}
function iconBgClass(c) {
    return {
        sky:     'bg-sky-100 text-sky-700 ring-sky-200',
        emerald: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
        violet:  'bg-violet-100 text-violet-700 ring-violet-200',
        amber:   'bg-amber-100 text-amber-700 ring-amber-200',
        rose:    'bg-rose-100 text-rose-700 ring-rose-200',
        slate:   'bg-slate-100 text-slate-700 ring-slate-200',
    }[c]
}

async function markOneRead(n) {
    if (n.read) return
    try {
        await window.axios.post(route('notifications.read', n.id))
        n.read = true
        unreadCount.value = Math.max(0, unreadCount.value - 1)
        emit('update:unreadCount', unreadCount.value)
    } catch (e) { /* silent */ }
}

async function markAllRead() {
    try {
        await window.axios.post(route('notifications.read-all'))
        for (const k of Object.keys(groups.value)) {
            groups.value[k].forEach(n => { n.read = true })
        }
        unreadCount.value = 0
        emit('update:unreadCount', 0)
    } catch (e) { /* silent */ }
}

function openAction(n) {
    markOneRead(n)
    if (n.action_url) {
        router.visit(n.action_url)
        emit('close')
    }
}

const groupLabels = {
    today: 'Today',
    yesterday: 'Yesterday',
    this_week: 'This Week',
    older: 'Earlier',
}
</script>

<template>
    <!-- Backdrop -->
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0" enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150"
        leave-from-class="opacity-100" leave-to-class="opacity-0"
    >
        <div v-if="open" class="fixed inset-0 z-[60] bg-black/30 backdrop-blur-sm" @click="emit('close')" />
    </Transition>

    <!-- Drawer -->
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full" enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0" leave-to-class="translate-x-full"
    >
        <aside v-if="open"
            class="fixed top-0 right-0 z-[70] h-full w-full max-w-md bg-base-100 shadow-2xl flex flex-col"
            role="dialog" aria-label="Notifications">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-base-200">
                <div class="flex items-center gap-3">
                    <div class="relative w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <BellIcon class="w-5 h-5" />
                        <span v-if="unreadCount > 0"
                            class="absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 rounded-full bg-rose-500 text-white text-[10px] font-bold flex items-center justify-center">
                            {{ unreadCount > 99 ? '99+' : unreadCount }}
                        </span>
                    </div>
                    <div>
                        <h2 class="font-bold text-base">Notifications</h2>
                        <p class="text-[11px] text-base-content/55">
                            <template v-if="unreadCount">{{ unreadCount }} unread</template>
                            <template v-else>All caught up</template>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-1">
                    <button v-if="unreadCount > 0" @click="markAllRead"
                        class="btn btn-ghost btn-xs rounded-lg gap-1 text-[11px]">
                        <CheckCircleIcon class="w-3.5 h-3.5" /> Mark all read
                    </button>
                    <button @click="emit('close')" class="btn btn-ghost btn-sm btn-square rounded-lg" aria-label="Close">
                        <XMarkIcon class="w-5 h-5" />
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto">
                <!-- Loading -->
                <div v-if="loading && totalShown === 0" class="p-10 text-center">
                    <span class="loading loading-spinner loading-md text-primary" />
                    <p class="text-xs text-base-content/55 mt-3">Loading notifications...</p>
                </div>

                <!-- Error -->
                <div v-else-if="errorMsg" class="p-6">
                    <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                        {{ errorMsg }}
                        <button @click="fetchRecent" class="ml-2 link link-primary text-xs">Retry</button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="totalShown === 0" class="p-10 text-center">
                    <div class="w-16 h-16 mx-auto rounded-2xl bg-base-200 flex items-center justify-center mb-4">
                        <InboxIcon class="w-8 h-8 text-base-content/30" />
                    </div>
                    <p class="text-sm font-semibold text-base-content/75">You're all caught up!</p>
                    <p class="text-xs text-base-content/50 mt-1">New notifications will appear here.</p>
                </div>

                <!-- Grouped list -->
                <div v-else>
                    <template v-for="(items, key) in groups" :key="key">
                        <div v-if="items.length" class="border-b border-base-200/60 last:border-0">
                            <div class="px-5 py-2.5 bg-base-200/40 sticky top-0 z-10">
                                <h3 class="text-[10px] uppercase tracking-[0.15em] font-bold text-base-content/55">
                                    {{ groupLabels[key] }} <span class="text-base-content/35 font-medium ml-1">· {{ items.length }}</span>
                                </h3>
                            </div>
                            <ul>
                                <li v-for="n in items" :key="n.id"
                                    class="group relative px-5 py-3.5 border-b border-base-200/50 last:border-0 hover:bg-base-200/40 transition-colors cursor-pointer"
                                    :class="{ 'bg-primary/[0.025]': !n.read }"
                                    @click="openAction(n)">
                                    <!-- Unread dot -->
                                    <span v-if="!n.read" class="absolute left-2 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-primary"></span>

                                    <div class="flex items-start gap-3 ml-2">
                                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 ring-1"
                                            :class="iconBgClass(meta(n).color)">
                                            <component :is="meta(n).icon" class="w-4 h-4" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <h4 class="font-semibold text-sm text-slate-900 leading-snug">{{ n.title }}</h4>
                                                <span class="text-[10px] text-base-content/45 whitespace-nowrap mt-0.5">{{ n.time_ago }}</span>
                                            </div>
                                            <p v-if="n.message" class="text-[12px] text-base-content/65 mt-1 leading-relaxed line-clamp-2">{{ n.message }}</p>
                                            <div v-if="n.action_label" class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-primary group-hover:gap-1.5 transition-all">
                                                {{ n.action_label }}
                                                <ArrowRightIcon class="w-3 h-3" />
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3 border-t border-base-200 flex items-center justify-between bg-base-200/30">
                <p class="text-[11px] text-base-content/55">Showing latest {{ totalShown }}</p>
                <Link :href="route('notifications.index')" @click="emit('close')"
                    class="text-xs font-semibold text-primary hover:underline inline-flex items-center gap-1">
                    See all <ArrowRightIcon class="w-3 h-3" />
                </Link>
            </div>
        </aside>
    </Transition>
</template>
