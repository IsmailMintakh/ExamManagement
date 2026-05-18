<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { usePermissions } from '@/Composables/usePermissions'
import {
    Squares2X2Icon, TableCellsIcon, Cog6ToothIcon,
    ArrowsRightLeftIcon, ChartBarIcon,
} from '@heroicons/vue/24/outline'

/**
 * Compact, dense tab bar shared by every page in the Timetable section.
 * Gives one-click navigation + a consistent visual anchor. Admin-only
 * tabs are filtered out for plain teachers.
 */
const props = defineProps({
    // Optional school id carried through to school-scoped routes.
    schoolId: { type: [Number, String], default: null },
})

const { isSuperAdmin, hasRole } = usePermissions()
const isAdmin = computed(() => isSuperAdmin.value || hasRole('school-admin'))

const page = usePage()
const current = computed(() => page.url.split('?')[0])

const tabs = computed(() => {
    const q = props.schoolId ? { school_id: props.schoolId } : {}
    const all = [
        { key: 'home', label: 'Overview', icon: Squares2X2Icon, href: route('timetable.index', q), match: /^\/timetable\/?$/ },
        { key: 'master', label: 'Master grid', icon: TableCellsIcon, href: route('timetable.master', q), match: /^\/timetable\/master/, admin: true },
        { key: 'setup', label: 'Bell schedule', icon: Cog6ToothIcon, href: route('timetable.setup', q), match: /^\/timetable\/setup/, admin: true },
        { key: 'adj', label: 'Class adjustments', icon: ArrowsRightLeftIcon, href: route('timetable.adjustments'), match: /^\/timetable\/adjustments/, admin: true },
        { key: 'reports', label: 'Reports', icon: ChartBarIcon, href: route('timetable.reports', q), match: /^\/timetable\/reports/, admin: true },
    ]
    return all.filter(t => !t.admin || isAdmin.value)
})

function active(t) {
    return t.match.test(current.value)
}
</script>

<template>
    <!-- Only render for admins (>1 tab). Teachers see a single personal
         view and don't need section navigation. -->
    <nav v-if="tabs.length > 1"
        class="flex items-center gap-1 overflow-x-auto rounded-xl border border-base-300 bg-base-100 p-1">
        <Link v-for="t in tabs" :key="t.key" :href="t.href"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="active(t)
                ? 'bg-primary text-primary-content'
                : 'text-base-content/65 hover:bg-base-200 hover:text-base-content'">
            <component :is="t.icon" class="h-3.5 w-3.5" />
            {{ t.label }}
        </Link>
    </nav>
</template>
