<script setup>
/**
 * Mobile-only bottom tab bar — fixed to the bottom of the viewport on
 * screens < lg. Tabs are role-aware (different primary actions per role).
 * The "More" tab opens the slide-out sidebar drawer for everything else.
 *
 * Redesigned to feel like a real native app: floating rounded bar, animated
 * pill on the active tab, elevated primary action, iOS-style spring on tap.
 */
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import {
    HomeIcon, AcademicCapIcon, ClipboardDocumentListIcon, DocumentTextIcon,
    ChartBarIcon, BellIcon, UserGroupIcon, Bars3Icon, BuildingOfficeIcon,
    QuestionMarkCircleIcon,
    UserCircleIcon,
} from '@heroicons/vue/24/outline'
import {
    HomeIcon as HomeIconSolid, AcademicCapIcon as AcademicCapIconSolid,
    ClipboardDocumentListIcon as MarksIconSolid,
    DocumentTextIcon as DocsIconSolid,
    ChartBarIcon as ChartIconSolid,
    BellIcon as BellIconSolid,
    UserGroupIcon as UsersIconSolid,
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
 * Order matters: most-used action goes in the middle for thumb-reach.
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

    // Primary action — the centerpiece of the bar (elevated FAB tile).
    if (user.value?.isClassTeacher) {
        items.push({ label: 'My Class', href: '/my-class', icon: AcademicCapIcon, iconActive: AcademicCapIconSolid, primary: true })
    } else if (hasPerm('marks.enter')) {
        items.push({ label: 'Marks', href: '/marks', icon: DocumentTextIcon, iconActive: DocsIconSolid, primary: true })
    } else if (hasPerm('exams.view')) {
        items.push({ label: 'Exams', href: '/exams', icon: ClipboardDocumentListIcon, iconActive: MarksIconSolid, primary: true })
    } else if (hasPerm('schools.view')) {
        items.push({ label: 'Schools', href: '/schools', icon: BuildingOfficeIcon, iconActive: BuildingIconSolid, primary: true })
    }

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
    <!-- Floating bar with a rounded silhouette + strong blur — reads as a
         real "app bar" rather than a browser toolbar strip. -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 pointer-events-none px-2 pb-2"
         style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem);"
         aria-label="Primary navigation">
        <div class="mx-auto max-w-xl pointer-events-auto rounded-2xl bg-base-100/85 backdrop-blur-2xl
                    border border-base-content/[0.08] shadow-[0_10px_30px_-8px_rgba(15,23,42,0.15)]
                    dark:shadow-[0_10px_30px_-8px_rgba(0,0,0,0.5)]">
            <div class="flex items-stretch justify-around px-1.5 pt-1.5 pb-1">
                <Link
                    v-for="tab in tabs" :key="tab.href + tab.label"
                    :href="tab.href"
                    class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-1.5 min-h-[54px]
                           touch-manipulation transition-transform active:scale-95"
                    :class="[
                        tab.primary ? '-mt-4' : '',
                    ]"
                >
                    <!-- Primary action: elevated round-square tile that
                         pops above the bar line. Feels like the app's
                         "signature" action. -->
                    <template v-if="tab.primary">
                        <div
                            class="w-14 h-14 rounded-2xl flex items-center justify-center transition-all"
                            :class="isActive(tab.href)
                                ? 'bg-gradient-to-br from-primary to-teal-600 text-white shadow-lg shadow-primary/30 scale-105'
                                : 'bg-gradient-to-br from-primary/90 to-teal-600/90 text-white shadow-md shadow-primary/25'">
                            <component :is="isActive(tab.href) ? tab.iconActive : tab.icon" class="w-6 h-6" />
                        </div>
                        <span class="text-[10px] font-bold leading-none mt-1.5"
                              :class="isActive(tab.href) ? 'text-primary' : 'text-base-content/60'">
                            {{ tab.label }}
                        </span>
                    </template>

                    <!-- Standard tab -->
                    <template v-else>
                        <!-- Pill background on the active tab (iOS/Android look). -->
                        <span v-if="isActive(tab.href)"
                              class="absolute top-1 left-1/2 -translate-x-1/2 w-10 h-8 rounded-xl bg-primary/12 dark:bg-primary/20"></span>

                        <div class="relative z-[1]">
                            <component :is="isActive(tab.href) ? tab.iconActive : tab.icon"
                                       class="w-6 h-6 transition-colors"
                                       :class="isActive(tab.href) ? 'text-primary' : 'text-base-content/55'" />
                            <span v-if="tab.badge && tab.badge > 0"
                                  class="absolute -top-1 -right-2 min-w-[16px] h-4 px-1 rounded-full
                                         bg-rose-500 text-white text-[9px] font-bold flex items-center justify-center
                                         ring-2 ring-base-100">
                                {{ tab.badge > 99 ? '99+' : tab.badge }}
                            </span>
                        </div>
                        <span class="text-[10px] font-semibold leading-none mt-1 transition-colors relative z-[1]"
                              :class="isActive(tab.href) ? 'text-primary' : 'text-base-content/60'">
                            {{ tab.label }}
                        </span>
                    </template>
                </Link>

                <!-- More — opens the full sidebar drawer -->
                <button
                    @click="emit('open-drawer')"
                    class="relative flex-1 flex flex-col items-center justify-center gap-0.5 py-1.5 min-h-[54px]
                           text-base-content/60 hover:text-base-content transition-transform active:scale-95
                           touch-manipulation"
                    aria-label="Open full menu"
                >
                    <Bars3Icon class="w-6 h-6" />
                    <span class="text-[10px] font-semibold leading-none mt-1">More</span>
                </button>
            </div>
        </div>
    </nav>
</template>
