<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import {
    AcademicCapIcon, BuildingOfficeIcon, UserGroupIcon,
    ClipboardDocumentListIcon, DocumentTextIcon, ChartBarIcon,
    BookOpenIcon, SparklesIcon, CalendarIcon, ArrowPathIcon,
    MagnifyingGlassIcon, QuestionMarkCircleIcon,
    LifebuoyIcon, EnvelopeIcon, ArrowRightIcon, CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const page = usePage()
const roles = computed(() => page.props.auth?.user?.roles || [])
const hasRole = (r) => roles.value.includes(r)

const search = ref('')
const activeCategory = ref('getting-started')

const categories = [
    { key: 'getting-started', label: 'Getting Started', icon: SparklesIcon },
    { key: 'exams', label: 'Exams & Marks', icon: ClipboardDocumentListIcon },
    { key: 'results', label: 'Results', icon: ChartBarIcon },
    { key: 'students', label: 'Students', icon: UserGroupIcon },
    { key: 'admin', label: 'Administration', icon: BuildingOfficeIcon },
]

const articles = [
    { category: 'getting-started', title: 'Welcome to ExamPro', content: 'ExamPro is a complete exam management system for districts and schools. Use the sidebar to navigate between modules. Your role determines what you can see and do.' },
    { category: 'getting-started', title: 'Understanding your role', content: 'DDO (Super Admin) manages all schools. Principal manages one school. Class Teachers manage their section. Subject Teachers enter marks for assigned subjects.' },
    { category: 'getting-started', title: 'First-time setup checklist', content: '1. Create an Academic Session. 2. Add Schools. 3. Create Users (Principals, Teachers). 4. Define Subjects and Exam Types. 5. Configure Grading Scales. 6. Add Classes & Sections. 7. Enroll Students.' },

    { category: 'exams', title: 'Creating an exam', content: 'DDO creates exams from Exams → Create. Configure name, type, academic session, dates, and passing rules (total marks, passing percentage, grace marks). Then add subjects per class and select schools.' },
    { category: 'exams', title: 'Publishing an exam', content: 'Draft exams must be published before teachers can enter marks. Click "Publish" on the exam show page. Then click "Open Marks Entry" to allow teachers to start entering marks.' },
    { category: 'exams', title: 'Entering marks', content: 'Subject Teachers go to Marks Entry, click their exam, and enter marks per student. Save as Draft to continue later, or Submit when done (submitted marks are locked).' },
    { category: 'exams', title: 'Locking marks entry', content: 'When deadline passes, click "Lock Entry" on the exam. Teachers can no longer edit. Unlock if corrections are needed.' },

    { category: 'results', title: 'Generating results', content: 'Principal goes to Results → View Exam → Generate. System auto-calculates totals, percentages, grades, positions using the exam rules.' },
    { category: 'results', title: 'Submitting to DDO', content: 'After review, Principal clicks "Submit to DDO". DDO sees the submission in Result Review queue and can approve or return for correction.' },
    { category: 'results', title: 'Generating mark sheets', content: 'From Results → View Section, click a student\'s name to download their mark sheet. Or click "All Mark Sheets" to get a multi-page PDF for the whole section.' },
    { category: 'results', title: 'Supplementary exams', content: 'Students who failed within threshold can appear for supplementary. Go to Supplementary → Select exam → Mark eligible students → Enter supplementary marks.' },

    { category: 'students', title: 'Adding a student', content: 'Class Teachers add students of their section. Go to Students → Add Student. Fill in admission number, name, father\'s name, DOB, and assign to class/section.' },
    { category: 'students', title: 'Bulk importing students', content: 'Use Students → Import to upload a CSV/Excel file. Download the sample template first. System validates and reports any errors.' },
    { category: 'students', title: 'Student transfer', content: 'For inter-school transfers, initiate from Students → Transfers. Receiving principal approves, student\'s academic history transfers automatically.' },
    { category: 'students', title: 'Promotion to next class', content: 'At year-end, Principal runs Student Promotion. Choose per-student: Promote / Retain / Graduate. Bulk actions available.' },

    { category: 'admin', title: 'Roles & permissions', content: 'DDO can customize permissions per role from Administration → Roles & Permissions. Super Admin role is locked for safety.' },
    { category: 'admin', title: 'Academic sessions', content: 'Create new sessions each year. Mark one as "Current" — all new data goes into it. Previous sessions become archived.' },
    { category: 'admin', title: 'Custom result card templates', content: 'DDO uploads custom HTML templates per academic year under Master Data → Result Templates. Use placeholders like {{student_name}}, {{percentage}}.' },
]

const filtered = computed(() => {
    let items = articles
    if (activeCategory.value !== 'all') items = items.filter(a => a.category === activeCategory.value)
    if (search.value.trim()) {
        const q = search.value.toLowerCase()
        items = items.filter(a => a.title.toLowerCase().includes(q) || a.content.toLowerCase().includes(q))
    }
    return items
})

const quickLinks = computed(() => [
    { label: 'Exams', href: '/exams', icon: ClipboardDocumentListIcon, color: 'primary', show: true },
    { label: 'Marks Entry', href: '/marks', icon: DocumentTextIcon, color: 'secondary', show: true },
    { label: 'Results', href: '/results', icon: ChartBarIcon, color: 'success', show: true },
    { label: 'Students', href: '/students', icon: UserGroupIcon, color: 'accent', show: true },
    { label: 'Settings', href: '/settings', icon: BuildingOfficeIcon, color: 'info', show: hasRole('super-admin') || hasRole('school-admin') },
])
</script>

<template>
    <Head title="Help & Documentation" />
    <AppLayout :breadcrumbs="[{ label: 'Help' }]">
        <div class="space-y-6">
            <!-- Hero -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary via-primary to-secondary p-8 text-primary-content shadow-xl shadow-primary/30">
                <div class="relative z-10 max-w-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 backdrop-blur-sm">
                            <LifebuoyIcon class="w-5 h-5" />
                        </div>
                        <p class="text-xs font-bold uppercase tracking-widest opacity-75">Help Center</p>
                    </div>
                    <h1 class="mt-4 text-3xl font-extrabold tracking-tight">How can we help you?</h1>
                    <p class="mt-2 text-sm opacity-85">Find answers to common questions, learn how to use features, and get the most out of ExamPro.</p>
                    <!-- Search -->
                    <div class="mt-6 relative max-w-lg">
                        <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-base-content/45" />
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Search help articles..."
                            class="w-full rounded-xl bg-white/95 text-base-content py-3 pl-11 pr-4 text-sm shadow-lg placeholder-base-content/45"
                        />
                    </div>
                </div>
                <div class="absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/5 blur-2xl" />
                <div class="absolute -bottom-16 -right-4 h-40 w-40 rounded-full bg-white/5 blur-3xl" />
            </div>

            <!-- Quick Links -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <template v-for="q in quickLinks" :key="q.label">
                    <Link v-if="q.show" :href="q.href" class="group flex flex-col items-start gap-2 rounded-xl border border-base-200 bg-base-100 p-4 transition-all hover:-translate-y-1 hover:shadow-md">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg transition-transform group-hover:scale-110" :class="`bg-${q.color}/10`">
                            <component :is="q.icon" class="h-5 w-5" :class="`text-${q.color}`" />
                        </div>
                        <p class="text-[13px] font-semibold">{{ q.label }}</p>
                        <ArrowRightIcon class="h-3 w-3 text-base-content/30 group-hover:text-primary" />
                    </Link>
                </template>
            </div>

            <!-- Categories + Articles -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
                <!-- Sidebar -->
                <aside class="surface">
                    <div class="surface-body space-y-1">
                        <p class="px-2 pb-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40">Categories</p>
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            @click="activeCategory = cat.key"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium transition-all"
                            :class="activeCategory === cat.key ? 'bg-primary/10 text-primary font-semibold' : 'text-base-content/65 hover:bg-base-200'"
                        >
                            <component :is="cat.icon" class="h-4 w-4" />
                            {{ cat.label }}
                        </button>
                    </div>
                </aside>

                <!-- Content -->
                <div class="lg:col-span-3 space-y-3">
                    <div v-if="filtered.length === 0" class="surface">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <QuestionMarkCircleIcon class="h-7 w-7 text-base-content/30" />
                            </div>
                            <h3 class="text-base font-bold">No articles found</h3>
                            <p class="mt-1.5 text-sm text-base-content/55">Try a different search term or category.</p>
                        </div>
                    </div>

                    <details v-for="(a, i) in filtered" :key="i" class="surface group" :open="i === 0">
                        <summary class="flex cursor-pointer items-center justify-between p-5 list-none">
                            <div class="flex items-center gap-3">
                                <CheckCircleIcon class="h-5 w-5 text-primary" />
                                <h3 class="text-sm font-bold">{{ a.title }}</h3>
                            </div>
                            <ArrowRightIcon class="h-4 w-4 text-base-content/40 transition-transform group-open:rotate-90" />
                        </summary>
                        <div class="px-5 pb-5 pl-[52px] text-[13px] leading-relaxed text-base-content/70">{{ a.content }}</div>
                    </details>
                </div>
            </div>

            <!-- Contact -->
            <div class="surface">
                <div class="surface-body flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary/10">
                            <EnvelopeIcon class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold">Still need help?</h3>
                            <p class="mt-0.5 text-xs text-base-content/60">Contact your school administrator or the DDO office.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
