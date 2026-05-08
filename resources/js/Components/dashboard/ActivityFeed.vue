<script setup>
/**
 * Compact activity feed — recent notifications surfaced as a chronological
 * stream. Maps icon names from notification payloads to heroicons.
 *
 * Props: items: [{ title, message, icon, url, when, when_iso, is_unread }]
 */
import {
    BellIcon, ClipboardDocumentCheckIcon, DocumentCheckIcon, AcademicCapIcon,
    CheckCircleIcon, ArrowsRightLeftIcon, BellAlertIcon, ChartBarIcon,
    UserMinusIcon, EnvelopeIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    items: { type: Array, default: () => [] },
})

const ICON_MAP = {
    'bell': BellIcon,
    'bell-alert': BellAlertIcon,
    'clipboard-check': ClipboardDocumentCheckIcon,
    'document-check': DocumentCheckIcon,
    'academic-cap': AcademicCapIcon,
    'check-circle': CheckCircleIcon,
    'arrows-right-left': ArrowsRightLeftIcon,
    'chart-bar': ChartBarIcon,
    'user-minus': UserMinusIcon,
    'envelope': EnvelopeIcon,
}
function iconFor(name) {
    return ICON_MAP[name] || BellIcon
}
</script>

<template>
    <div>
        <div v-if="items.length" class="space-y-0">
            <a v-for="item in items" :key="item.id"
                :href="item.url || '#'"
                class="group block px-4 py-3 -mx-1 transition-colors hover:bg-base-200/40 first:rounded-t-xl last:rounded-b-xl"
                :class="{ 'bg-primary/[0.03]': item.is_unread }">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 ring-1 ring-base-200"
                        :class="item.is_unread ? 'bg-primary/10 text-primary ring-primary/20' : 'bg-base-200 text-base-content/55'">
                        <component :is="iconFor(item.icon)" class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <p class="font-bold text-sm leading-tight truncate"
                                :class="item.is_unread ? 'text-base-content' : 'text-base-content/85'">
                                {{ item.title }}
                            </p>
                            <span v-if="item.is_unread" class="w-1.5 h-1.5 rounded-full bg-primary shrink-0"></span>
                        </div>
                        <p class="text-xs text-base-content/65 mt-0.5 truncate">{{ item.message }}</p>
                        <p class="text-[10px] text-base-content/45 mt-1">{{ item.when }}</p>
                    </div>
                </div>
            </a>
        </div>
        <div v-else class="px-4 py-10 text-center">
            <BellIcon class="w-8 h-8 text-base-content/25 mx-auto mb-2" />
            <p class="text-sm text-base-content/55 font-medium">No recent activity</p>
            <p class="text-xs text-base-content/40 mt-0.5">Notifications and updates will show up here.</p>
        </div>
    </div>
</template>
