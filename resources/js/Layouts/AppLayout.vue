<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import {
    Bars3Icon, XMarkIcon, BellIcon, ChevronDownIcon,
    UserCircleIcon, ArrowRightStartOnRectangleIcon,
    HomeIcon, BuildingOfficeIcon, AcademicCapIcon, BookOpenIcon,
    UserGroupIcon, ClipboardDocumentListIcon, ChartBarIcon,
    DocumentTextIcon, Cog6ToothIcon, UsersIcon, CalendarIcon,
    ClipboardDocumentCheckIcon, TableCellsIcon, ShieldCheckIcon,
    BellAlertIcon, ClockIcon, ChevronRightIcon, ChevronLeftIcon,
    ArrowPathIcon, CheckBadgeIcon, ArrowsRightLeftIcon,
    ArchiveBoxIcon, DocumentDuplicateIcon, MagnifyingGlassIcon,
    SparklesIcon, ExclamationTriangleIcon, Squares2X2Icon,
    LifebuoyIcon, QuestionMarkCircleIcon, TrophyIcon,
    GlobeAltIcon, PhotoIcon, NewspaperIcon, EnvelopeIcon,
    ShieldExclamationIcon,
} from '@heroicons/vue/24/outline'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import Toast from '@/Components/Toast.vue'
import CommandPalette from '@/Components/CommandPalette.vue'
import NotificationDrawer from '@/Components/NotificationDrawer.vue'
import MobileBottomNav from '@/Components/MobileBottomNav.vue'
import PWAManager from '@/Components/PWAManager.vue'

const props = defineProps({
    breadcrumbs: { type: Array, default: () => [] },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const currentSession = computed(() => page.props.auth?.currentSession)
const sessions = computed(() => page.props.auth?.sessions || [])
const notificationCount = computed(() => page.props.notificationCount || 0)
const contactMessageCount = computed(() => page.props.contactMessageCount || 0)
const roles = computed(() => user.value?.roles || [])
const permissions = computed(() => user.value?.permissions || [])

const hasRole = (r) => roles.value.includes(r)
const hasPerm = (p) => hasRole('super-admin') || permissions.value.includes(p)

const currentPath = computed(() => page.url)
const isActive = (href) => {
    if (href === '/dashboard') return currentPath.value === '/dashboard'
    return currentPath.value?.startsWith(href)
}

// ─── Auto-scroll the active sidebar link into view ───
// When the user navigates to a deep menu item, the long sidebar list often
// hides the active item below the fold. We scroll it into view after each
// route change so the user can always see "you are here".
const sidebarNavRef = ref(null)
function scrollActiveLinkIntoView() {
    nextTick(() => {
        const nav = sidebarNavRef.value
        if (!nav) return
        const active = nav.querySelector('.sidebar-link.active')
        if (!active) return
        // 'nearest' avoids unnecessary scrolling when the item is already visible.
        active.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
    })
}
watch(currentPath, () => scrollActiveLinkIntoView())
onMounted(() => scrollActiveLinkIntoView())

// ─── Mobile back-button logic ───
// Prefer the second-last breadcrumb (the parent page) as the back target.
// Falls back to /dashboard. Hidden when there's nowhere to go back to —
// e.g. on dashboard itself or single-crumb top-level pages.
const canShowBack = computed(() => {
    if (props.breadcrumbs.length >= 2) return true
    // Single-crumb pages get back-to-dashboard if not already on dashboard.
    return currentPath.value && currentPath.value !== '/dashboard' && currentPath.value !== '/'
})
const parentHref = computed(() => {
    if (props.breadcrumbs.length >= 2) {
        return props.breadcrumbs[props.breadcrumbs.length - 2]?.href || '/dashboard'
    }
    return '/dashboard'
})

// ---- Collapsible group state (persisted) ----
const STORAGE_KEY = 'sidebar_collapsed_groups'
const collapsedGroups = ref(new Set(JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')))

function toggleGroup(label) {
    if (collapsedGroups.value.has(label)) {
        collapsedGroups.value.delete(label)
    } else {
        collapsedGroups.value.add(label)
    }
    // trigger reactivity on Set
    collapsedGroups.value = new Set(collapsedGroups.value)
    localStorage.setItem(STORAGE_KEY, JSON.stringify([...collapsedGroups.value]))
}

const isGroupCollapsed = (group) => {
    // Always expand the group containing the active route
    if (group.items?.some(i => isActive(i.href))) return false
    // Overview (dashboard) is always expanded — only one item
    if (group.label === 'Overview' || group.label === 'My Account' || group.label === 'Family') return false
    return collapsedGroups.value.has(group.label)
}

const roleBadge = computed(() => {
    if (hasRole('super-admin')) return { label: 'DDO', color: 'text-primary bg-primary/10' }
    if (hasRole('school-admin')) return { label: 'Principal', color: 'text-secondary bg-secondary/10' }
    if (hasRole('class-teacher')) return { label: 'Class Teacher', color: 'text-accent bg-accent/10' }
    if (hasRole('subject-teacher')) return { label: 'Subject Teacher', color: 'text-info bg-info/10' }
    if (hasRole('student')) return { label: 'Student', color: 'text-success bg-success/10' }
    if (hasRole('parent')) return { label: 'Parent', color: 'text-warning bg-warning/10' }
    return { label: 'User', color: 'text-base-content/60 bg-base-content/10' }
})

// ----- Navigation Structure -----
const menuGroups = computed(() => {
    // ─── Unified Family Portal (student + parent share one nav) ───
    // Student sees their own dashboard; parent sees the same page with
    // a child picker. Same routes, controller adapts via role.
    if (hasRole('student') || hasRole('parent')) {
        return [{
            label: hasRole('parent') ? 'Family' : 'My Account',
            items: [
                { label: 'Dashboard', href: '/portal/dashboard', icon: HomeIcon },
                { label: 'Results', href: '/portal/results', icon: ChartBarIcon },
                { label: 'Notifications', href: '/notifications', icon: BellAlertIcon },
            ],
        }]
    }

    // ─── Teacher-only menu (focused, no admin-tooling clutter) ───
    // Class-teachers and subject-teachers see only what they can actually
    // act on: their class/sections, marks entry, their results. Admin
    // sections (Schools, Users, Master Data, Website, etc.) are hidden
    // because they'd resolve to empty/403 pages anyway.
    const isAdminish = hasRole('super-admin') || hasRole('school-admin')
    const isTeacher = hasRole('class-teacher') || hasRole('subject-teacher')
    if (isTeacher && !isAdminish) {
        // Gate "My Class" on the ACTUAL section assignment, not on the role
        // alone. The class-teacher role can exist on a user who isn't currently
        // assigned as class_teacher_id of any active section (e.g. the role
        // was granted but the assignment was moved to someone else). Showing
        // "My Class" to those users dumps them on a 403 page. The middleware
        // (HandleInertiaRequests) already computes this boolean by checking
        // Section::where('class_teacher_id', user.id)->active()->exists().
        const isClassTeacher = !!user.value?.isClassTeacher

        // ── My Class (class-teacher hub) — each tab is a direct sidebar
        //    link via ?tab= so the whole hub is reachable in one click. ──
        const classItems = []
        if (isClassTeacher) {
            classItems.push(
                { label: 'Overview', href: '/my-class?tab=overview', icon: Squares2X2Icon },
                { label: 'Students', href: '/my-class?tab=students', icon: UserGroupIcon },
                { label: 'Marks & Results', href: '/my-class?tab=marks', icon: ClipboardDocumentCheckIcon },
                { label: 'Class Timetable', href: '/my-class?tab=timetable', icon: CalendarIcon },
                { label: 'Reports', href: '/my-class?tab=reports', icon: DocumentTextIcon },
                { label: 'Section Team', href: '/my-class?tab=team', icon: UsersIcon },
            )
        }

        // ── Teaching (every teacher gets the same set regardless of
        //    current subject assignments). Pages enforce access internally. ──
        const teachItems = [
            { label: 'My Subjects', href: '/my-subjects', icon: BookOpenIcon },
            { label: 'Marks Entry', href: '/marks', icon: DocumentTextIcon },
        ]
        // Assessment marks — only for class teachers of primary-stage
        // sections (ECD–5). The 10-mark overall conduct/participation score
        // that feeds the primary Annual Result aggregation.
        if (user.value?.isPrimaryClassTeacher) {
            teachItems.push({ label: 'Assessment', href: '/assessment', icon: CheckBadgeIcon })
        }
        if (hasPerm('exams.view')) teachItems.push({ label: 'Exams', href: '/exams', icon: ClipboardDocumentListIcon })
        teachItems.push({ label: 'Smart Lesson Plan', href: '/lesson-plan', icon: SparklesIcon })
        if (hasPerm('questions.view')) teachItems.push({ label: 'Question Bank', href: '/questions', icon: QuestionMarkCircleIcon })
        if (hasPerm('papers.view')) teachItems.push({ label: 'Paper Generator', href: '/papers', icon: DocumentDuplicateIcon })

        // ── Schedule (personal only — no school-wide timetable hub) ──
        const scheduleItems = [
            { label: 'My Timetable', href: '/timetable/teacher/' + (user.value?.id ?? ''), icon: CalendarIcon },
            { label: 'My Class Adjustments', href: '/my-adjustments', icon: ArrowsRightLeftIcon },
        ]

        const teacherGroups = [
            { label: 'Overview', items: [{ label: 'Dashboard', href: '/dashboard', icon: HomeIcon }] },
        ]
        if (classItems.length) teacherGroups.push({ label: 'My Class', items: classItems })
        if (teachItems.length) teacherGroups.push({ label: 'Teaching', items: teachItems })
        teacherGroups.push({ label: 'Schedule', items: scheduleItems })
        teacherGroups.push({
            label: 'System',
            items: [
                { label: 'Notifications', href: '/notifications', icon: BellAlertIcon },
                { label: 'Help & Docs', href: '/help', icon: LifebuoyIcon },
            ],
        })
        return teacherGroups
    }

    const groups = []
    groups.push({
        label: 'Overview',
        items: [{ label: 'Dashboard', href: '/dashboard', icon: HomeIcon }],
    })

    const workflowItems = []
    // Class teachers get a prominent "My Class" entry at the top — but only
    // if they're actually assigned to a section. Role alone isn't enough;
    // the page itself enforces an assignment check and would 403.
    if (user.value?.isClassTeacher) workflowItems.push({ label: 'My Class', href: '/my-class', icon: AcademicCapIcon })
    if (hasPerm('exams.view')) workflowItems.push({ label: 'Exams', href: '/exams', icon: ClipboardDocumentListIcon })
    if (hasPerm('marks.view') || hasPerm('marks.enter')) workflowItems.push({ label: 'Marks Entry', href: '/marks', icon: DocumentTextIcon })
    if (hasRole('super-admin') || hasRole('school-admin')) workflowItems.push({ label: 'Marks Progress', href: '/marks/progress', icon: ClipboardDocumentCheckIcon })
    if (hasPerm('results.view')) workflowItems.push({ label: 'Results', href: '/results', icon: ChartBarIcon })
    if (hasPerm('results.review')) workflowItems.push({ label: 'Result Review', href: '/result-review', icon: CheckBadgeIcon })
    if (hasPerm('supplementary.view')) workflowItems.push({ label: 'Supplementary', href: '/supplementary', icon: ArrowPathIcon })
    if (hasPerm('scheduling.view')) workflowItems.push({ label: 'Exam Scheduling', href: '/scheduling', icon: CalendarIcon })
    // Timetable + daily substitution. Available to admins (build the timetable)
    // and to teachers (read-only view of their own schedule).
    workflowItems.push({ label: 'Timetable', href: '/timetable', icon: CalendarIcon })
    if (hasPerm('reports.view')) workflowItems.push({ label: 'Reports', href: '/reports', icon: DocumentTextIcon })
    if (hasPerm('analytics.view')) workflowItems.push({ label: 'Analytics', href: '/analytics', icon: SparklesIcon })
    workflowItems.push({ label: 'Smart Lesson Plan', href: '/lesson-plan', icon: SparklesIcon })
    if (hasPerm('questions.view')) workflowItems.push({ label: 'Question Bank', href: '/questions', icon: QuestionMarkCircleIcon })
    if (hasPerm('papers.view')) workflowItems.push({ label: 'Paper Generator', href: '/papers', icon: DocumentDuplicateIcon })
    if (hasPerm('certificates.view')) workflowItems.push({ label: 'Certificates', href: '/certificates', icon: TrophyIcon })
    if (workflowItems.length) groups.push({ label: 'Workflow', items: workflowItems })

    if (hasPerm('students.view')) {
        const studentItems = [{ label: 'All Students', href: '/students', icon: UserGroupIcon }]
        if (hasPerm('transfers.view')) studentItems.push({ label: 'Transfers', href: '/transfers', icon: ArrowsRightLeftIcon })
        if (hasPerm('promotion.view')) studentItems.push({ label: 'Promotion', href: '/promotion', icon: ArrowPathIcon })
        groups.push({ label: 'Students', items: studentItems })
    }

    const academicItems = []
    if (hasPerm('classes.view')) academicItems.push({ label: 'Classes', href: '/classes', icon: AcademicCapIcon })
    if (hasPerm('sections.view')) academicItems.push({ label: 'Sections', href: '/sections', icon: ClipboardDocumentCheckIcon })
    if (hasPerm('teacher-assignments.view')) academicItems.push({ label: 'Teacher Assignments', href: '/teacher-assignments', icon: ShieldCheckIcon })
    if (academicItems.length) groups.push({ label: 'Academic', items: academicItems })

    if (hasPerm('insights.view')) {
        groups.push({
            label: 'Insights',
            items: [
                { label: 'Student Progress', href: '/insights/student-progress', icon: ChartBarIcon },
                { label: 'At-Risk Students', href: '/insights/at-risk', icon: ExclamationTriangleIcon },
                { label: 'Subject Heatmap', href: '/insights/subject-heatmap', icon: Squares2X2Icon },
                { label: 'Teacher Performance', href: '/insights/teacher-performance', icon: AcademicCapIcon },
            ],
        })
    }

    const adminItems = []
    if (hasPerm('schools.view')) adminItems.push({ label: 'Schools', href: '/schools', icon: BuildingOfficeIcon })
    if (hasPerm('users.view')) adminItems.push({ label: 'Users & Staff', href: '/users', icon: UsersIcon })
    if (hasPerm('roles.view')) adminItems.push({ label: 'Roles & Permissions', href: '/roles', icon: ShieldCheckIcon })
    if (hasPerm('sessions.view')) adminItems.push({ label: 'Academic Sessions', href: '/academic-sessions', icon: CalendarIcon })
    if (adminItems.length) groups.push({ label: 'Administration', items: adminItems })

    const masterItems = []
    if (hasPerm('subjects.view')) masterItems.push({ label: 'Subjects', href: '/subjects', icon: BookOpenIcon })
    if (hasPerm('exam-types.view')) masterItems.push({ label: 'Exam Types', href: '/exam-types', icon: TableCellsIcon })
    if (hasPerm('grading.view')) masterItems.push({ label: 'Grading Scales', href: '/grading-scales', icon: ChartBarIcon })
    if (hasPerm('result-card-templates.view')) masterItems.push({ label: 'Result Templates', href: '/result-card-templates', icon: DocumentDuplicateIcon })
    if (hasPerm('certificates.templates.view')) masterItems.push({ label: 'Certificate Templates', href: '/certificates/templates', icon: TrophyIcon })
    if (masterItems.length) groups.push({ label: 'Master Data', items: masterItems })

    if (hasPerm('website.manage')) {
        groups.push({
            label: 'Website',
            items: [
                { label: 'Site Settings', href: '/website/settings', icon: GlobeAltIcon },
                { label: 'Hero Slider', href: '/website/hero-slides', icon: PhotoIcon },
                { label: 'News & Articles', href: '/website/news', icon: NewspaperIcon },
                { label: 'Photo Gallery', href: '/website/gallery', icon: Squares2X2Icon },
                { label: 'Faculty', href: '/website/faculty', icon: UserGroupIcon },
                { label: 'Pages Content', href: '/website/pages', icon: DocumentTextIcon },
                { label: 'Contact Messages', href: '/website/contact-messages', icon: EnvelopeIcon, badge: 'contactMessageCount' },
            ],
        })
    }

    const sysItems = []
    if (hasPerm('notifications.view')) sysItems.push({ label: 'Notifications', href: '/notifications', icon: BellAlertIcon })
    if (hasPerm('archive.view')) sysItems.push({ label: 'Archive', href: '/archive', icon: ArchiveBoxIcon })
    if (hasPerm('activity.view')) sysItems.push({ label: 'Activity Log', href: '/activity-log', icon: ClockIcon })
    if (hasPerm('settings.view')) sysItems.push({ label: 'Settings', href: '/settings', icon: Cog6ToothIcon })
    if (hasRole('super-admin')) sysItems.push({ label: 'Data Cleanup', href: '/admin/data-cleanup', icon: ShieldExclamationIcon })
    sysItems.push({ label: 'Help & Docs', href: '/help', icon: LifebuoyIcon })
    groups.push({ label: 'System', items: sysItems })

    return groups
})

const sidebarOpen = ref(false)
// Desktop-only collapse state. Persisted across reloads so the user's choice
// sticks. When collapsed the sidebar shrinks to icons-only (72px) — full
// labels reappear on hover via the floating tooltip below.
const sidebarCollapsed = ref(localStorage.getItem('sidebar-collapsed') === '1')
function toggleSidebarCollapsed() {
    sidebarCollapsed.value = !sidebarCollapsed.value
    localStorage.setItem('sidebar-collapsed', sidebarCollapsed.value ? '1' : '0')
}

// ─── Floating sidebar tooltip ───
// CSS-only pseudo-element tooltips kept getting clipped by the aside's
// overflow rules. We render a SINGLE position:fixed tip outside the aside
// and reposition it on hover. JS-driven but trivial — one setter per event.
const tip = ref({ visible: false, label: '', top: 0, left: 0 })
function showTip(event, label) {
    if (!sidebarCollapsed.value) return
    const rect = event.currentTarget.getBoundingClientRect()
    tip.value = {
        visible: true,
        label,
        // Vertically center the tip on the link.
        top: rect.top + rect.height / 2,
        // Sit just to the right of the sidebar (72px) with a small gap.
        left: rect.right + 8,
    }
}
function hideTip() { tip.value.visible = false }
const userMenuOpen = ref(false)
const sessionMenuOpen = ref(false)
const schoolMenuOpen = ref(false)
const commandPalette = ref(null)
const notificationDrawerOpen = ref(false)
const liveUnreadCount = ref(null)
function onUnreadUpdate(c) { liveUnreadCount.value = c }

function closeSidebar() { sidebarOpen.value = false }
function logout() { router.post(route('logout')) }

function switchSession(sessionId) {
    sessionMenuOpen.value = false
    router.post(route('academic-sessions.switch', sessionId))
}

// DDO school selector — TWO modes:
//   1. Super-admin, not impersonating → click a school to LOG IN AS its
//      principal (Auth::login). "All schools" clears view-scope on the
//      DDO account (no impersonation).
//   2. Super-admin already impersonating → click another school to switch
//      accounts, or click "Return to DDO" to log back in as the DDO.
// Session-only, no DB writes. See ImpersonationController.
const schools = computed(() => page.props.auth?.schools || [])
const viewingSchoolId = computed(() => page.props.auth?.viewingSchoolId || null)
const viewingSchool = computed(() => schools.value.find(s => s.id === viewingSchoolId.value) || null)
const impersonation = computed(() => page.props.auth?.impersonation || { active: false })
const isImpersonating = computed(() => impersonation.value.active === true)

function switchViewingSchool(schoolId) {
    schoolMenuOpen.value = false
    // "All schools" → clear the view scope. Also functions as "leave
    // impersonation" if currently impersonating.
    if (!schoolId) {
        if (isImpersonating.value) {
            router.post(route('impersonate.leave'), {}, { preserveScroll: false })
        } else {
            router.post(route('viewing-school.set'), { school_id: null }, { preserveScroll: true })
        }
        return
    }
    // A school was picked. Always start (or restart) impersonation —
    // the DDO wants to sign in as that school's principal, not just
    // scope reads.
    router.post(route('impersonate.start', schoolId), {}, { preserveScroll: false })
}

function returnToDdo() {
    schoolMenuOpen.value = false
    router.post(route('impersonate.leave'), {}, { preserveScroll: false })
}

// Page transition key: use ONLY the pathname (not the query string) so
// that partial reloads triggered by search inputs (e.g. /exams?search=e
// → /exams?search=ex) don't destroy and recreate the whole content
// subtree. Real navigations (/exams → /students) still change the key
// and re-trigger the fade-in animation.
const pagePathKey = computed(() => (page.url || '').split('?')[0])

function handleOutsideClick(e) {
    if (userMenuOpen.value && !e.target.closest('.user-menu-container')) userMenuOpen.value = false
    if (sessionMenuOpen.value && !e.target.closest('.session-menu-container')) sessionMenuOpen.value = false
    if (schoolMenuOpen.value && !e.target.closest('.school-menu-container')) schoolMenuOpen.value = false
}

onMounted(() => document.addEventListener('click', handleOutsideClick))
onUnmounted(() => document.removeEventListener('click', handleOutsideClick))

watch(() => page.url, () => { sidebarOpen.value = false })
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-base-200">
        <!-- ============ MOBILE OVERLAY ============ -->
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            enter-from-class="opacity-0" enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100" leave-to-class="opacity-0"
        >
            <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 backdrop-blur-md lg:hidden" @click="closeSidebar" />
        </Transition>

        <!-- ============ SIDEBAR ============ -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex flex-col bg-base-100 transition-all duration-300 ease-out lg:relative lg:z-auto lg:translate-x-0"
            :class="[
                sidebarOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
                sidebarCollapsed ? 'lg:w-[72px]' : 'lg:w-[286px]',
                'w-[286px]',
            ]"
            :data-collapsed="sidebarCollapsed ? 'true' : 'false'"
            style="border-right: 1px solid oklch(var(--bc) / 0.08);"
        >
            <!-- Desktop collapse toggle — floating tab on the right edge -->
            <button
                @click="toggleSidebarCollapsed"
                class="hidden lg:flex sidebar-collapse-btn"
                :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
            >
                <ChevronLeftIcon class="w-3.5 h-3.5 chev" />
            </button>

            <!-- Brand / Logo — premium teal-to-deep-teal gradient -->
            <div class="flex h-16 shrink-0 items-center gap-3 px-5" style="border-bottom: 1px solid oklch(var(--bc) / 0.08);">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl shadow-lg shadow-primary/20"
                     style="background: linear-gradient(135deg, #14b8a6 0%, #0d9488 50%, #0f766e 100%);">
                    <AcademicCapIcon class="h-5 w-5 text-white" />
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[15px] font-extrabold tracking-tight leading-none">ExamPro</p>
                    <p class="text-[10px] uppercase tracking-[0.18em] text-base-content/45 font-semibold mt-1">Management</p>
                </div>
                <button class="btn btn-ghost btn-xs btn-circle lg:hidden" @click="closeSidebar">
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>

            <!-- User Card — refined with avatar, name, role pill -->
            <div class="mx-3 mt-3 mb-2 flex items-center gap-2.5 rounded-xl px-2.5 py-2.5" style="background: oklch(var(--bc) / 0.04); border: 1px solid oklch(var(--bc) / 0.05);">
                <div class="h-9 w-9 rounded-full text-white flex items-center justify-center text-xs font-bold shrink-0 overflow-hidden"
                     style="background: linear-gradient(135deg, #4b545c 0%, #2c3138 100%);">
                    <img v-if="user?.avatar" :src="user.avatar" :alt="user.name" class="w-full h-full object-cover" />
                    <span v-else>{{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[12.5px] font-semibold truncate leading-tight">{{ user?.name }}</p>
                    <span class="inline-flex items-center mt-0.5 rounded-md px-1.5 py-0.5 text-[9.5px] font-bold uppercase tracking-wider" :class="roleBadge.color">
                        {{ roleBadge.label }}
                    </span>
                </div>
            </div>

            <!-- Navigation -->
            <nav ref="sidebarNavRef" class="flex-1 overflow-y-auto px-2.5 pt-1 pb-4">
                <div v-for="group in menuGroups" :key="group.label" class="mb-0.5">
                    <!-- Single-item groups (Overview, My Account, Family) don't collapse -->
                    <template v-if="group.items?.length <= 1 || ['Overview','My Account','Family'].includes(group.label)">
                        <p class="sidebar-section">{{ group.label }}</p>
                        <div class="space-y-0.5">
                            <Link
                                v-for="item in group.items"
                                :key="item.href"
                                :href="item.href"
                                class="sidebar-link"
                                :class="{ active: isActive(item.href) }"
                                :title="item.label"
                                @mouseenter="showTip($event, item.label)"
                                @mouseleave="hideTip"
                                @focus="showTip($event, item.label)"
                                @blur="hideTip"
                            >
                                <component :is="item.icon" class="sidebar-icon" />
                                <span class="flex-1 truncate">{{ item.label }}</span>
                                <span v-if="item.href === '/notifications' && notificationCount > 0"
                                    class="flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-error px-1.5 text-[9px] font-bold text-error-content">
                                    {{ notificationCount > 99 ? '99+' : notificationCount }}
                                </span>
                                <span v-else-if="item.badge === 'contactMessageCount' && contactMessageCount > 0"
                                    class="flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-emerald-500 px-1.5 text-[9px] font-bold text-white">
                                    {{ contactMessageCount > 99 ? '99+' : contactMessageCount }}
                                </span>
                            </Link>
                        </div>
                    </template>

                    <!-- Collapsible groups -->
                    <template v-else>
                        <button
                            @click="toggleGroup(group.label)"
                            class="sidebar-group-toggle group flex w-full items-center gap-1 rounded-md px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-[0.1em] text-base-content/45 hover:text-base-content/75 hover:bg-base-content/[0.04] transition-colors"
                        >
                            <ChevronRightIcon
                                class="h-3 w-3 transition-transform duration-200"
                                :class="isGroupCollapsed(group) ? '' : 'rotate-90'"
                            />
                            <span class="flex-1 text-left">{{ group.label }}</span>
                            <span class="text-[9px] font-semibold text-base-content/30 opacity-0 group-hover:opacity-100 transition-opacity">
                                {{ group.items.length }}
                            </span>
                        </button>
                        <Transition
                            enter-active-class="grid transition-all duration-200 ease-out"
                            enter-from-class="grid-rows-[0fr] opacity-0"
                            enter-to-class="grid-rows-[1fr] opacity-100"
                            leave-active-class="grid transition-all duration-150 ease-in"
                            leave-from-class="grid-rows-[1fr] opacity-100"
                            leave-to-class="grid-rows-[0fr] opacity-0"
                        >
                            <div v-show="!isGroupCollapsed(group)" class="overflow-hidden">
                                <div class="space-y-0.5 py-0.5">
                                    <Link
                                        v-for="item in group.items"
                                        :key="item.href"
                                        :href="item.href"
                                        class="sidebar-link"
                                        :class="{ active: isActive(item.href) }"
                                        :title="item.label"
                                        @mouseenter="showTip($event, item.label)"
                                        @mouseleave="hideTip"
                                        @focus="showTip($event, item.label)"
                                        @blur="hideTip"
                                    >
                                        <component :is="item.icon" class="sidebar-icon" />
                                        <span class="flex-1 truncate">{{ item.label }}</span>
                                        <span v-if="item.href === '/notifications' && notificationCount > 0"
                                            class="flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-error px-1.5 text-[9px] font-bold text-error-content">
                                            {{ notificationCount > 99 ? '99+' : notificationCount }}
                                        </span>
                                    </Link>
                                </div>
                            </div>
                        </Transition>
                    </template>
                </div>
            </nav>

            <!-- DDO School Selector — super-admin (or DDO currently
                 impersonating a principal). Clicking a school actually
                 logs you IN AS that school's principal via Laravel's
                 Auth::login. "Return to DDO account" logs back in as
                 the original DDO. See ImpersonationController. -->
            <div v-if="(hasRole('super-admin') || isImpersonating) && schools.length > 1"
                 class="shrink-0 p-3" style="border-top: 1px solid oklch(var(--bc) / 0.08);">
                <div class="school-menu-container relative">
                    <button
                        @click.stop="schoolMenuOpen = !schoolMenuOpen"
                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-xs font-medium transition-all"
                        :style="isImpersonating
                            ? 'background: oklch(var(--wa) / 0.10); border: 1px solid oklch(var(--wa) / 0.30);'
                            : 'background: oklch(var(--bc) / 0.04); border: 1px solid oklch(var(--bc) / 0.06);'"
                    >
                        <BuildingOfficeIcon class="h-4 w-4 shrink-0"
                            :class="isImpersonating ? 'text-warning' : 'text-secondary'" />
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-[9px] uppercase tracking-widest leading-none"
                                :class="isImpersonating ? 'text-warning' : 'text-base-content/40'">
                                {{ isImpersonating ? 'Impersonating' : 'Viewing' }}
                            </p>
                            <p class="mt-0.5 truncate text-[12.5px] font-semibold leading-tight">
                                {{ isImpersonating ? impersonation.current_school_name : (viewingSchool?.name || 'All schools') }}
                            </p>
                        </div>
                        <ChevronDownIcon class="h-3.5 w-3.5 text-base-content/40 transition-transform" :class="{ 'rotate-180': schoolMenuOpen }" />
                    </button>
                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="schoolMenuOpen" class="absolute bottom-full left-0 right-0 mb-2 rounded-xl bg-base-100 p-1.5 shadow-lifted max-h-[60vh] overflow-y-auto" style="border: 1px solid oklch(var(--bc) / 0.1);">
                            <!-- Top action: "Return to DDO" when impersonating,
                                 or "All schools" district view otherwise. -->
                            <button v-if="isImpersonating"
                                @click="returnToDdo"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-semibold bg-warning/10 text-warning-content hover:bg-warning/20 transition-colors mb-1"
                                :title="`Log back in as ${impersonation.original_name || 'DDO'}`">
                                <span class="h-1.5 w-1.5 rounded-full bg-warning" />
                                <span class="flex-1 text-left">← Return to DDO account</span>
                            </button>
                            <button v-else @click="switchViewingSchool(null)"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs transition-colors"
                                :class="viewingSchoolId === null ? 'bg-secondary/10 text-secondary font-semibold' : 'hover:bg-base-200'">
                                <span class="h-1.5 w-1.5 rounded-full" :class="viewingSchoolId === null ? 'bg-secondary' : 'bg-base-300'" />
                                <span class="flex-1 text-left">All schools (district view)</span>
                                <CheckBadgeIcon v-if="viewingSchoolId === null" class="h-3.5 w-3.5 text-secondary" />
                            </button>
                            <div class="h-px bg-base-200 my-1"></div>
                            <button v-for="s in schools" :key="s.id"
                                @click="switchViewingSchool(s.id)"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs transition-colors"
                                :class="(isImpersonating ? impersonation.current_school_id : viewingSchoolId) === s.id
                                    ? (isImpersonating ? 'bg-warning/10 text-warning-content font-semibold' : 'bg-secondary/10 text-secondary font-semibold')
                                    : 'hover:bg-base-200'">
                                <span class="h-1.5 w-1.5 rounded-full"
                                    :class="(isImpersonating ? impersonation.current_school_id : viewingSchoolId) === s.id
                                        ? (isImpersonating ? 'bg-warning' : 'bg-secondary')
                                        : 'bg-base-300'" />
                                <span class="flex-1 text-left truncate">{{ s.name }}</span>
                                <CheckBadgeIcon v-if="(isImpersonating ? impersonation.current_school_id : viewingSchoolId) === s.id"
                                    class="h-3.5 w-3.5"
                                    :class="isImpersonating ? 'text-warning' : 'text-secondary'" />
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Session Selector -->
            <div v-if="sessions?.length" class="shrink-0 p-3" style="border-top: 1px solid oklch(var(--bc) / 0.08);">
                <div class="session-menu-container relative">
                    <button
                        @click.stop="sessionMenuOpen = !sessionMenuOpen"
                        class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-xs font-medium transition-all"
                        style="background: oklch(var(--bc) / 0.04); border: 1px solid oklch(var(--bc) / 0.06);"
                    >
                        <CalendarIcon class="h-4 w-4 text-primary shrink-0" />
                        <div class="flex-1 text-left min-w-0">
                            <p class="text-[9px] uppercase tracking-widest text-base-content/40 leading-none">Session</p>
                            <p class="mt-0.5 truncate text-[12.5px] font-semibold leading-tight">{{ currentSession?.name || 'Select' }}</p>
                        </div>
                        <ChevronDownIcon class="h-3.5 w-3.5 text-base-content/40 transition-transform" :class="{ 'rotate-180': sessionMenuOpen }" />
                    </button>
                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="sessionMenuOpen" class="absolute bottom-full left-0 right-0 mb-2 rounded-xl bg-base-100 p-1.5 shadow-lifted" style="border: 1px solid oklch(var(--bc) / 0.1);">
                            <button
                                v-for="session in sessions"
                                :key="session.id"
                                @click="switchSession(session.id)"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-xs transition-colors"
                                :class="currentSession?.id === session.id ? 'bg-primary/10 text-primary font-semibold' : 'hover:bg-base-200'"
                            >
                                <span class="h-1.5 w-1.5 rounded-full" :class="currentSession?.id === session.id ? 'bg-primary' : 'bg-base-300'" />
                                <span class="flex-1 text-left">{{ session.name }}</span>
                                <CheckBadgeIcon v-if="currentSession?.id === session.id" class="h-3.5 w-3.5 text-primary" />
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>

            <!-- Developer credit — sits at the bottom of the sidebar, always visible -->
            <div class="shrink-0 px-3 py-2.5 text-center text-[10px] leading-snug text-base-content/45"
                 style="border-top: 1px solid oklch(var(--bc) / 0.06);">
                <p class="font-semibold text-base-content/55">Built by Ismail Hussain</p>
                <a href="tel:+923479089715" class="hover:text-primary transition-colors tabular-nums">
                    +92 347 9089715
                </a>
            </div>
        </aside>

        <!-- ============ MAIN CONTENT ============ -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- Top Bar -->
            <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 px-4 sm:gap-3 sm:px-6 glass-strong" style="border-bottom: 1px solid oklch(var(--bc) / 0.08);">
                <!-- MOBILE: Back button (when not on dashboard) OR menu button -->
                <Link v-if="canShowBack" :href="parentHref" class="lg:hidden flex items-center justify-center w-10 h-10 -ml-2 rounded-full active:bg-base-200 transition-colors touch-manipulation" aria-label="Go back">
                    <ChevronLeftIcon class="h-5 w-5" />
                </Link>
                <button v-else class="lg:hidden flex items-center justify-center w-10 h-10 -ml-2 rounded-full active:bg-base-200 transition-colors touch-manipulation" @click="sidebarOpen = true" aria-label="Open navigation menu">
                    <Bars3Icon class="h-5 w-5" />
                </button>

                <!-- Mobile: app-style title — bigger, bolder than breadcrumbs -->
                <div v-if="breadcrumbs.length" class="lg:hidden flex flex-col min-w-0 leading-tight">
                    <span class="text-base font-bold truncate text-base-content">{{ breadcrumbs[breadcrumbs.length - 1].label }}</span>
                    <span v-if="breadcrumbs.length > 1" class="text-[10px] uppercase tracking-wider text-base-content/45 font-semibold truncate">
                        {{ breadcrumbs[breadcrumbs.length - 2].label }}
                    </span>
                </div>

                <!-- Desktop: full breadcrumbs -->
                <nav v-if="breadcrumbs.length" class="hidden items-center gap-1.5 text-[13px] lg:flex">
                    <Link :href="route('dashboard')" class="flex h-7 w-7 items-center justify-center rounded-md text-base-content/40 transition-all hover:bg-base-200 hover:text-primary">
                        <HomeIcon class="h-4 w-4" />
                    </Link>
                    <template v-for="(crumb, i) in breadcrumbs" :key="i">
                        <ChevronRightIcon class="h-3.5 w-3.5 text-base-content/25" />
                        <Link
                            v-if="crumb.href && i < breadcrumbs.length - 1"
                            :href="crumb.href"
                            class="rounded-md px-2 py-1 font-medium text-base-content/55 transition-all hover:bg-base-200 hover:text-primary"
                        >
                            {{ crumb.label }}
                        </Link>
                        <span v-else class="rounded-md px-2 py-1 font-semibold text-base-content/85">{{ crumb.label }}</span>
                    </template>
                </nav>

                <div class="flex-1" />

                <!-- Command Palette Button -->
                <button
                    @click="commandPalette?.openPalette()"
                    class="hidden sm:flex items-center gap-2 rounded-xl border border-base-200 bg-base-200/40 px-3 py-1.5 text-xs text-base-content/55 transition-colors hover:border-primary/30 hover:bg-base-200"
                >
                    <MagnifyingGlassIcon class="h-4 w-4" />
                    <span>Search...</span>
                    <kbd class="rounded border border-base-300 bg-base-100 px-1.5 py-0.5 text-[9px] font-bold">⌘K</kbd>
                </button>

                <!-- Theme Toggle -->
                <ThemeToggle />

                <!-- Notifications -->
                <button @click="notificationDrawerOpen = true"
                    class="btn btn-ghost btn-sm btn-square relative"
                    :aria-label="`Notifications${notificationCount ? ' (' + notificationCount + ' unread)' : ''}`">
                    <BellIcon class="h-[18px] w-[18px]" />
                    <span v-if="(liveUnreadCount ?? notificationCount) > 0"
                        class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-error text-error-content text-[9px] font-bold flex items-center justify-center">
                        {{ ((liveUnreadCount ?? notificationCount) > 99) ? '99+' : (liveUnreadCount ?? notificationCount) }}
                    </span>
                </button>

                <!-- User Menu -->
                <div class="user-menu-container relative">
                    <button
                        @click.stop="userMenuOpen = !userMenuOpen"
                        class="flex items-center gap-2 rounded-xl py-1 pl-1 pr-2 transition-colors hover:bg-base-200 sm:gap-2.5 sm:pr-2.5"
                    >
                        <div class="avatar-initial h-8 w-8 text-xs">
                            {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                        </div>
                        <div class="hidden text-left sm:block">
                            <p class="text-xs font-semibold leading-tight">{{ user?.name }}</p>
                            <p class="text-[10px] text-base-content/50 mt-0.5">{{ roleBadge.label }}</p>
                        </div>
                        <ChevronDownIcon class="hidden h-3.5 w-3.5 text-base-content/40 sm:block" />
                    </button>

                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95 -translate-y-1"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div v-if="userMenuOpen" class="absolute right-0 top-full mt-2 w-72 max-w-[calc(100vw-1rem)] origin-top-right rounded-xl bg-base-100 shadow-lifted" style="border: 1px solid oklch(var(--bc) / 0.1);">
                            <div class="flex items-center gap-3 px-4 py-3.5 rounded-t-xl" style="background: linear-gradient(135deg, oklch(var(--p) / 0.08), oklch(var(--s) / 0.05));">
                                <div class="avatar-initial h-10 w-10 shrink-0">
                                    {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold break-words leading-tight">{{ user?.name }}</p>
                                    <p class="text-[11px] text-base-content/55 break-all leading-tight mt-0.5">{{ user?.email }}</p>
                                </div>
                            </div>
                            <div class="p-1.5">
                                <Link :href="route('profile.edit')" @click="userMenuOpen = false"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-[13px] transition-colors hover:bg-base-200 active:bg-base-300">
                                    <UserCircleIcon class="h-4 w-4 text-base-content/55 shrink-0" />
                                    <span>My Profile</span>
                                </Link>
                                <Link v-if="hasPerm('settings.view') || hasRole('school-admin')" :href="route('settings.index')" @click="userMenuOpen = false"
                                    class="flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-[13px] transition-colors hover:bg-base-200 active:bg-base-300">
                                    <Cog6ToothIcon class="h-4 w-4 text-base-content/55 shrink-0" />
                                    <span>Settings</span>
                                </Link>
                            </div>
                            <div class="p-1.5 rounded-b-xl" style="border-top: 1px solid oklch(var(--bc) / 0.06);">
                                <button @click="logout"
                                    class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2.5 text-[13px] text-error transition-colors hover:bg-error/10 active:bg-error/20">
                                    <ArrowRightStartOnRectangleIcon class="h-4 w-4 shrink-0" />
                                    <span>Sign Out</span>
                                </button>
                            </div>
                        </div>
                    </Transition>
                </div>
            </header>

            <!-- Page Content with transition. pb-24 lg:pb-6 reserves space for mobile bottom nav.
                 overflow-x-hidden defends against any inner element accidentally pushing the page wider than the viewport on mobile. -->
            <main class="flex-1 overflow-y-auto overflow-x-hidden">
                <!-- Impersonation banner — always visible when a DDO is
                     signed in as a school's principal. One-click "Return to
                     my account" so the DDO can't forget they're impersonating. -->
                <div v-if="isImpersonating"
                    class="sticky top-0 z-40 border-b border-warning/30 bg-warning/10 text-warning-content px-4 py-2 text-sm flex items-center gap-3">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-warning text-white text-xs font-bold shrink-0">!</span>
                    <div class="flex-1 min-w-0">
                        <span class="font-semibold">You are signed in as {{ impersonation.current_school_name }}'s principal.</span>
                        <span class="opacity-75 hidden sm:inline"> · Your DDO account: {{ impersonation.original_name }}</span>
                    </div>
                    <button @click="returnToDdo" class="btn btn-sm btn-warning gap-1.5 shrink-0">
                        Return to my DDO account
                    </button>
                </div>
                <div :key="pagePathKey" class="mx-auto max-w-7xl px-4 py-5 pb-28 sm:px-6 sm:py-6 lg:px-8 lg:pb-6 animate-fade-in">
                    <slot />
                </div>
            </main>
        </div>

        <!-- Floating sidebar tooltip — rendered as a sibling of <aside> so
             nothing in the aside's tree can clip it. Shown only when the
             sidebar is collapsed (showTip bails early otherwise). -->
        <div
            v-show="tip.visible"
            class="sidebar-tip"
            :class="{ 'is-visible': tip.visible }"
            :style="{ top: tip.top + 'px', left: tip.left + 'px' }"
            role="tooltip"
            aria-hidden="true"
        >
            {{ tip.label }}
        </div>

        <!-- Mobile bottom tab bar — hidden on lg+, opens drawer for "More" -->
        <MobileBottomNav @open-drawer="sidebarOpen = true" />

        <!-- PWA: install prompt, offline indicator, update prompt -->
        <PWAManager />

        <Toast />
        <CommandPalette ref="commandPalette" />
        <NotificationDrawer
            :open="notificationDrawerOpen"
            @close="notificationDrawerOpen = false"
            @update:unreadCount="onUnreadUpdate"
        />
    </div>
</template>
