<script setup>
defineProps({
    // Optional small label above the title — usually the parent area
    // ("Workflow", "Administration"). Renders uppercase + tracked.
    eyebrow: { type: String, default: '' },
    title: { type: String, required: true },
    // Optional one-line description under the title.
    subtitle: { type: String, default: '' },
    // Optional icon-pill on the left (component, e.g. AcademicCapIcon).
    // Renders only when set + sized to ~40px square.
    icon: { type: [Object, Function], default: null },
    // Tone for the icon-pill: emerald | sky | amber | violet | rose | primary.
    tone: { type: String, default: 'primary' },
})
</script>

<template>
    <!-- Standard page header used at the top of every list/show page.
         Keeps title + subtitle on the left and any actions on the right.
         Drop arbitrary buttons / filters into the #actions slot. -->
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between mb-1">
        <div class="flex items-start gap-3 min-w-0">
            <div v-if="icon"
                class="hidden sm:flex h-10 w-10 shrink-0 items-center justify-center rounded-xl"
                :class="{
                    'bg-primary/10 text-primary': tone === 'primary',
                    'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400': tone === 'emerald',
                    'bg-sky-500/10 text-sky-600 dark:text-sky-400': tone === 'sky',
                    'bg-amber-500/10 text-amber-600 dark:text-amber-400': tone === 'amber',
                    'bg-violet-500/10 text-violet-600 dark:text-violet-400': tone === 'violet',
                    'bg-rose-500/10 text-rose-600 dark:text-rose-400': tone === 'rose',
                }">
                <component :is="icon" class="w-5 h-5" />
            </div>
            <div class="min-w-0 flex-1">
                <p v-if="eyebrow"
                    class="text-[10px] uppercase tracking-[0.2em] font-bold text-base-content/55 mb-1">
                    {{ eyebrow }}
                </p>
                <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight leading-tight">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="text-sm text-base-content/55 mt-1">{{ subtitle }}</p>
            </div>
        </div>

        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:flex-shrink-0">
            <slot name="actions" />
        </div>
    </header>
</template>
