<script setup>
import { computed } from 'vue'
import { XMarkIcon, CheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    /** Number of selected items */
    count: { type: Number, default: 0 },
    /** Total items on the current page (for "select all" toggle) */
    total: { type: Number, default: 0 },
    /** Action buttons — array of { key, label, icon, variant?, danger? } */
    actions: { type: Array, default: () => [] },
    /** Whether everything on the page is currently selected */
    allSelected: { type: Boolean, default: false },
})

const emit = defineEmits(['action', 'clear', 'toggleAll'])

const visible = computed(() => props.count > 0)
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-4"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-4"
    >
        <div v-if="visible"
            class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40 max-w-[95vw] sm:max-w-3xl w-auto">
            <div class="rounded-2xl bg-slate-900 text-white shadow-2xl border border-slate-700 px-3 py-2.5 flex items-center gap-2 sm:gap-3">
                <!-- Count badge -->
                <div class="flex items-center gap-2 pl-2 pr-3 py-1 rounded-xl bg-emerald-500/15 ring-1 ring-emerald-500/30 flex-shrink-0">
                    <span class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center">
                        <CheckIcon class="w-3.5 h-3.5 text-white" />
                    </span>
                    <span class="text-xs sm:text-sm font-bold tabular-nums text-emerald-100">
                        {{ count }} selected
                    </span>
                </div>

                <!-- Select all toggle (when not all selected on this page) -->
                <button v-if="total > 0 && total !== count"
                    @click="emit('toggleAll')"
                    class="hidden sm:inline-flex items-center text-[11px] font-semibold text-slate-300 hover:text-white px-2 py-1 rounded-lg hover:bg-white/5 transition-colors whitespace-nowrap">
                    Select all {{ total }}
                </button>

                <span class="hidden sm:inline w-px h-6 bg-white/10"></span>

                <!-- Action buttons -->
                <div class="flex items-center gap-1 sm:gap-1.5 overflow-x-auto">
                    <button v-for="a in actions" :key="a.key"
                        @click="emit('action', a.key)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:py-2 rounded-xl text-xs sm:text-[13px] font-semibold whitespace-nowrap transition-all hover:scale-[1.02] active:scale-[0.98]"
                        :class="a.danger
                            ? 'bg-rose-500/15 text-rose-200 hover:bg-rose-500 hover:text-white ring-1 ring-rose-500/30'
                            : a.variant === 'primary'
                                ? 'bg-emerald-500 text-white hover:bg-emerald-400'
                                : 'bg-white/5 text-slate-200 hover:bg-white/15 ring-1 ring-white/10'">
                        <component :is="a.icon" v-if="a.icon" class="w-4 h-4" />
                        <span>{{ a.label }}</span>
                    </button>
                </div>

                <!-- Close -->
                <button @click="emit('clear')"
                    class="ml-auto sm:ml-1 p-1.5 rounded-lg hover:bg-white/10 text-slate-400 hover:text-white transition-colors flex-shrink-0"
                    title="Clear selection">
                    <XMarkIcon class="w-4 h-4" />
                </button>
            </div>
        </div>
    </Transition>
</template>
