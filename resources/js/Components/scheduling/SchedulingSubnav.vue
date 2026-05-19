<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import {
    ClipboardDocumentListIcon, Squares2X2Icon, CalendarDaysIcon,
    BuildingOffice2Icon, UserGroupIcon, IdentificationIcon,
} from '@heroicons/vue/24/outline'

/**
 * Shared step bar for the Exam-Scheduling workflow. Exam-scoped steps only
 * appear once an exam is selected; "All exams" and "Rooms" are always there.
 */
const props = defineProps({
    examId: { type: [Number, String], default: null },
})

const page = usePage()
const path = computed(() => page.url.split('?')[0])

const tabs = computed(() => {
    const id = props.examId
    const list = [
        { key: 'exams', label: 'All exams', icon: ClipboardDocumentListIcon,
          href: route('scheduling.exams'), match: /^\/scheduling\/?$/ },
    ]
    if (id) {
        list.push(
            { key: 'index', label: 'Overview', icon: Squares2X2Icon,
              href: route('scheduling.index', id), match: new RegExp(`^/scheduling/exams/${id}/?$`) },
            { key: 'datesheet', label: 'Date sheet', icon: CalendarDaysIcon,
              href: route('scheduling.datesheet', id), match: /\/datesheet/ },
        )
    }
    list.push({ key: 'rooms', label: 'Rooms', icon: BuildingOffice2Icon,
        href: route('scheduling.rooms'), match: /^\/scheduling\/rooms/ })
    if (id) {
        list.push(
            { key: 'invigilators', label: 'Invigilators', icon: UserGroupIcon,
              href: route('scheduling.invigilators', id), match: /\/invigilators/ },
            { key: 'admit', label: 'Admit cards', icon: IdentificationIcon,
              href: route('scheduling.admit-cards', id), match: /\/admit-cards/ },
        )
    }
    return list
})

const active = (t) => t.match.test(path.value)
</script>

<template>
    <nav class="flex items-center gap-1 overflow-x-auto rounded-xl border border-base-300 bg-base-100 p-1">
        <Link v-for="t in tabs" :key="t.key" :href="t.href"
            class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors"
            :class="active(t)
                ? 'bg-primary text-primary-content'
                : 'text-base-content/60 hover:bg-base-200 hover:text-base-content'">
            <component :is="t.icon" class="h-3.5 w-3.5" />
            {{ t.label }}
        </Link>
    </nav>
</template>
