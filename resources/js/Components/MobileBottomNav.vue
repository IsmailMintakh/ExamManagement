<script setup>
/**
 * Mobile-only bottom tab bar — fixed to the bottom of the viewport on screens
 * smaller than `lg`. Tabs are role-aware (different primary actions for class
 * teacher, subject teacher, principal, DDO, student, parent). The "More" tab
 * opens the existing slide-out sidebar drawer for everything else.
 *
 * Visual model: 5 tabs evenly spaced. The middle tab can act as a primary
 * action (currently mirrors the user's most-used action; e.g. "My Class" for
 * class teacher) — slightly elevated and accent-colored to feel like an
 * app-style FAB-tab hybrid.
 */
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    HomeIcon, AcademicCapIcon, ClipboardDocumentListIcon, DocumentTextIcon,
    ChartBarIcon, BellIcon, UserGroupIcon, Bars3Icon, BuildingOfficeIcon,
    QuestionMarkCircleIcon, DocumentDuplicateIcon, TrophyIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'
import {
    HomeIcon as HomeIconSolid, AcademicCapIcon as AcademicCapIconSolid,
    ClipboardDocumentListIcon as MarksIconSolid,
    DocumentTextIcon as DocsIconSolid,
    ChartBarIcon as ChartIconSolid,
    BellIcon as BellIconSolid,
    UserGroupIcon as UsersIconSolid,
    Bars3Icon as MenuIconSolid,
    BuildingOfficeIcon as BuildingIconSolid,
    UserCircleIcon as UserCircleIconSolid,
} from '@heroicons/vue/24/solid'

const emit = defineEmits(['open-drawer'])

const page = usePage()
const user = computed(() => page.props.auth?.user)
const roles = computed(() => user.value?.roles || [])
const permissions = computed(() => user.value?.permissions || [])
const notificationCount = computed(() => page.props.notificationCount || 0)

const hasRole = (r) => roles.value.includes(r)
const hasPerm = (p) => hasRole('super-admin') || permissions.value.includes(p)

const currentPath = computed(() => page.url)
const isActive = (href) => {
    if (!href) return false
    if (href === '/dashboard') return currentPath.value === '/dashboard'
    return currentPath.value?.startsWith(href)
}

/**
 * Role-aware tab set. Returns up to 4 navigation tabs + 1 "More" trigger.
 * Order matters: most important / most-used actions go in the middle for
 * thumb-reach.
 */
const tabs = computed(() => {
    if (hasRole('student')) {
        return [
            { label: 'Home',     href: '/my/dashboard',     icon: HomeIcon,  iconActive: HomeIconSolid },
            { label: 'Results',  href: '/my/results',       icon: ChartBarIcon, iconActive: ChartIconSolid },
            { label: 'Notifs',   href: '/notifications',    icon: BellIcon,  iconActive: BellIconSolid, badge: notificationCount.value },
            { label: 'Profile',  href: '/profile',          icon: UserCircleIcon, iconActive: UserCircleIconSolid },
        ]
    }
    if (hasRole('parent')) {
        return [
            { label: 'Home',     href: '/parent/dashboard', icon: HomeIcon,  iconActive: HomeIconSolid },
            { label: 'Children', href: '/parent/dashboard', icon: UserGroupIcon, iconActive: UsersIconSolid },
            { label: 'Notifs',   href: '/notifications',    icon: BellIcon,  iconActive: BellIconSolid, badge: notificationCount.value },
            { label: 'Profile',  href: '/profile',          icon: UserCircleIcon, iconActive: UserCircleIconSolid },
        ]
    }

    // Staff (DDO, Principal, Class Teacher, Subject Teacher)
    const items = [
        { label: 'Home', href: '/dashboard', icon: HomeIcon, iconActive: HomeIconSolid },
    ]

    // Primary action — the centerpiece of the bar (slightly elevated)
    if (hasRole('class-teacher')) {
        items.push({ label: 'My Class', href: '/my-class', icon: AcademicCapIcon, iconActive: AcademicCapIconSolid, primary: true })
    } else if (hasPerm('marks.enter')) {
        items.push({ label: 'Marks', href: '/marks', icon: DocumentTextIcon, iconActive: DocsIconSolid, primary: true })
    } else if (hasPerm('exams.view')) {
        items.push({ label: 'Exams', href: '/exams', icon: ClipboardDocumentListIcon, iconActive: MarksIconSolid, primary: true })
    } else if (hasPerm('schools.view')) {
        items.push({ label: 'Schools', href: '/schools', icon: BuildingOfficeIcon, iconActive: BuildingIconSolid, primary: true })
    }

    // Tertiary destinations (only the most-used per role)
    if (hasPerm('students.view')) {
        items.push({ label: 'Students', href: '/students', icon: UserGroupIcon, iconActive: UsersIconSolid })
    } else if (hasPerm('results.view')) {
        items.push({ label: 'Results', href: '/results', icon: ChartBarIcon, iconActive: ChartIconSolid })
    } else if (hasPerm('questions.view')) {
        items.push({ label: 'Questions', href: '/questions', icon: QuestionMarkCircleIcon, iconActive: QuestionMarkCircleIcon })
    }

    items.push({ label: 'Notifs', href: '/notifications', icon: BellIcon, iconActive: BellIconSolid, badge: notificationCount.value })

    return items
})
</script>

<template>
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-base-100/95 backdrop-blur-xl"
         style="border-top: 1px solid oklch(var(--bc) / 0.08); padding-bottom: env(safe-area-inset-bottom);"
         aria-label="Primary navigation">
        <div class="flex items-stretch justify-around max-w-xl mx-auto px-1">
            <Link
                v-for="tab in tabs" :key="tab.href"
                :href="tab.href"
                class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-2 transition-all touch-manipulation"
                :class="[
                    isActive(tab.href) ? 'text-primary' : 'text-base-content/55 hover:text-base-content/85',
                    tab.primary ? '-mt-3' : '',
                ]"
            >
                <!-- Primary action: elevated pill -->
                <div v-if="tab.primary"
                     class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-lg transition-all"
                     :class="isActive(tab.href)
                        ? 'bg-gradient-to-br from-primary to-primary/80 shadow-primary/30 text-white'
                        : 'bg-base-200 text-base-content/70'">
                    <component :is="isActive(tab.href) ? tab.iconActive : tab.icon" class="w-6 h-6" />
                </div>

                <!-- Standard tab -->
                <div v-else class="relative">
                    <component :is="isActive(tab.href) ? tab.iconActive : tab.icon" class="w-6 h-6" />
                    <span v-if="tab.badge && tab.badge > 0"
                          class="absolute -top-1 -right-2 min-w-[16px] h-4 px-1 rounded-full bg-error text-error-content text-[9px] font-bold flex items-center justify-center">
                        {{ tab.badge > 99 ? '99+' : tab.badge }}
                    </span>
                </div>

                <span class="text-[10px] font-semibold leading-none mt-1"
                      :class="tab.primary ? 'mt-0.5' : ''">{{ tab.label }}</span>

                <!-- Active indicator dot -->
                <span v-if="isActive(tab.href) && !tab.primary"
                      class="absolute top-1 w-1 h-1 rounded-full bg-primary"></span>
            </Link>

            <!-- More — opens existing slide-out drawer -->
            <button
                @click="emit('open-drawer')"
                class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-2 text-base-content/55 hover:text-base-content/85 transition-colors touch-manipulation"
                aria-label="Open full menu"
            >
                <Bars3Icon class="w-6 h-6" />
                <span class="text-[10px] font-semibold leading-none mt-1">More</span>
            </button>
        </div>
    </nav>
</template>
