<script setup>
/**
 * Board Results — index page. Shows every FBISE board-exam container
 * the user can see (school-scoped for non-super-admins). Each row links
 * into the students list for that exam.
 */
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/PageHeader.vue'
import { Head, Link } from '@inertiajs/vue3'
import {
    AcademicCapIcon, PlusIcon, DocumentCheckIcon, LockClosedIcon,
    UserGroupIcon, ClockIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    exams: { type: Array, default: () => [] },
    canCreate: { type: Boolean, default: false },
})

const fmtDate = (iso) => iso
    ? new Date(iso).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' })
    : '—'
</script>

<template>
    <Head title="Board Results" />
    <AppLayout :breadcrumbs="[{ label: 'Board Results' }]">
        <div class="space-y-4 sm:space-y-5 max-w-7xl mx-auto">
            <PageHeader
                title="FBISE Board Results"
                subtitle="9th / 10th official results — enter marks announced by FBISE and generate mark sheets."
                :icon="AcademicCapIcon" tone="violet">
                <template #actions>
                    <Link v-if="canCreate" :href="route('board-results.create')"
                        class="btn btn-primary btn-sm rounded-xl gap-1.5">
                        <PlusIcon class="w-4 h-4" />
                        New Board Exam
                    </Link>
                </template>
            </PageHeader>

            <!-- Empty state -->
            <div v-if="!exams.length"
                 class="rounded-2xl bg-base-100 border border-dashed border-base-300 p-8 sm:p-10 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                            flex items-center justify-center shadow-lg shadow-violet-500/25 mb-3">
                    <AcademicCapIcon class="w-7 h-7" />
                </div>
                <h3 class="text-lg font-bold">No board exams yet</h3>
                <p class="text-sm text-base-content/60 mt-1 max-w-md mx-auto">
                    Create a new exam container for the FBISE 9th or 10th result cycle, then enter the
                    students' marks announced in the gazette.
                </p>
                <Link v-if="canCreate" :href="route('board-results.create')"
                    class="btn btn-primary btn-sm rounded-xl gap-1.5 mt-4">
                    <PlusIcon class="w-4 h-4" />
                    Create your first board exam
                </Link>
            </div>

            <!-- Exams grid -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
                <Link v-for="e in exams" :key="e.id"
                    :href="route('board-results.show', e.id)"
                    class="group relative rounded-2xl bg-base-100 border border-base-300/70 shadow-sm
                           hover:shadow-md hover:-translate-y-0.5 transition-all p-5 overflow-hidden">
                    <div class="pointer-events-none absolute -top-12 -right-12 w-32 h-32 rounded-full
                                bg-violet-500/10 blur-2xl opacity-60"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-[0.18em] font-bold text-violet-600/80">
                                {{ e.board_name }} · {{ e.level }}
                            </p>
                            <h3 class="text-base font-bold mt-1 truncate group-hover:text-primary transition-colors">
                                {{ e.title }}
                            </h3>
                            <p class="text-xs text-base-content/60 mt-0.5 truncate">
                                {{ e.school_class?.name }} · {{ e.school?.name }}
                            </p>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-violet-500 to-fuchsia-600 text-white
                                    flex items-center justify-center shadow-md shadow-violet-500/25 shrink-0">
                            <AcademicCapIcon class="w-5 h-5" />
                        </div>
                    </div>

                    <div class="relative mt-4 flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1 text-base-content/60">
                            <UserGroupIcon class="w-3.5 h-3.5" />
                            <b class="text-base-content">{{ e.students_count }}</b> entered
                        </span>
                        <span class="inline-flex items-center gap-1 text-base-content/60">
                            <ClockIcon class="w-3.5 h-3.5" />
                            {{ fmtDate(e.announced_on) }}
                        </span>
                        <span v-if="e.is_locked"
                              class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider
                                     px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300">
                            <LockClosedIcon class="w-3 h-3" /> Locked
                        </span>
                    </div>

                    <div class="relative mt-4 pt-3 border-t border-base-200 flex items-center justify-between text-xs">
                        <span class="text-base-content/55">{{ e.academic_session?.name }}</span>
                        <span class="inline-flex items-center gap-0.5 font-semibold text-primary
                                     group-hover:translate-x-0.5 transition-transform">
                            Open <ChevronRightIcon class="w-3.5 h-3.5" />
                        </span>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
