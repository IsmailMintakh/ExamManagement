<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { ClockIcon, Cog6ToothIcon, ArrowLeftIcon } from '@heroicons/vue/24/outline'

defineProps({
    school: Object,
})
</script>

<template>
    <Head title="Bell Schedule Required" />
    <AppLayout :breadcrumbs="[
        { label: 'Timetable', href: route('timetable.index') },
        { label: 'Setup required' },
    ]">
        <div class="max-w-2xl mx-auto py-8">
            <Link :href="route('timetable.index')" class="btn btn-ghost btn-sm gap-1 mb-4 -ml-2">
                <ArrowLeftIcon class="w-4 h-4" /> Back
            </Link>

            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-8 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-500/15 grid place-items-center mb-4">
                    <ClockIcon class="w-8 h-8 text-amber-600 dark:text-amber-400" />
                </div>
                <h1 class="text-xl font-extrabold tracking-tight mb-2">
                    Bell schedule not configured
                </h1>
                <p class="text-sm text-base-content/65 mb-1">
                    Before building a class timetable, you need to define
                    <strong>{{ school?.name }}</strong>'s daily periods, breaks, and lunch.
                </p>
                <p class="text-xs text-base-content/45 mb-6">
                    This is a one-time setup — periods can have different durations and you can mark
                    Friday as a half-day by simply unchecking FRI on later periods.
                </p>
                <Link :href="route('timetable.setup', { school_id: school?.id })"
                    class="btn btn-primary rounded-xl gap-2">
                    <Cog6ToothIcon class="w-4 h-4" />
                    Set up bell schedule
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
