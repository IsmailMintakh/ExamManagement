<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    MagnifyingGlassIcon, XMarkIcon,
    UserIcon, ClipboardDocumentListIcon,
    AcademicCapIcon, BuildingOfficeIcon,
    UsersIcon, PlusIcon, DocumentTextIcon,
    ChartBarIcon, LifebuoyIcon,
} from '@heroicons/vue/24/outline'

const open = ref(false)
const query = ref('')
const loading = ref(false)
const results = ref([])
const quickActions = ref([])
const selectedIndex = ref(0)
const inputEl = ref(null)

const iconMap = {
    user: UserIcon,
    clipboard: ClipboardDocumentListIcon,
    academic: AcademicCapIcon,
    building: BuildingOfficeIcon,
    users: UsersIcon,
    plus: PlusIcon,
    document: DocumentTextIcon,
    chart: ChartBarIcon,
    help: LifebuoyIcon,
}

const flatItems = computed(() => {
    if (!query.value.trim()) return quickActions.value.map(a => ({ ...a, icon: a.icon }))
    return results.value.flatMap(group => group.items || [])
})

let timer = null
watch(query, (v) => {
    clearTimeout(timer)
    if (!v || v.length < 2) {
        results.value = []
        return
    }
    loading.value = true
    timer = setTimeout(() => {
        fetch(route('search') + '?q=' + encodeURIComponent(v), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => {
                results.value = (data.results || []).filter(g => g.items?.length)
                selectedIndex.value = 0
            })
            .finally(() => { loading.value = false })
    }, 200)
})

function openPalette() {
    open.value = true
    query.value = ''
    selectedIndex.value = 0
    nextTick(() => inputEl.value?.focus())
    loadQuickActions()
}
function closePalette() { open.value = false }

function loadQuickActions() {
    if (quickActions.value.length) return
    fetch(route('search.actions'), { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => { quickActions.value = data.actions || [] })
}

function navigate(url) {
    closePalette()
    router.visit(url)
}

function handleKeydown(e) {
    // Open with Ctrl+K or Cmd+K
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        if (open.value) closePalette(); else openPalette()
        return
    }
    if (!open.value) return
    if (e.key === 'Escape') { closePalette(); return }
    const items = flatItems.value
    if (e.key === 'ArrowDown') {
        e.preventDefault()
        selectedIndex.value = Math.min(selectedIndex.value + 1, items.length - 1)
    } else if (e.key === 'ArrowUp') {
        e.preventDefault()
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
    } else if (e.key === 'Enter') {
        e.preventDefault()
        const item = items[selectedIndex.value]
        if (item?.url) navigate(item.url)
    }
}

onMounted(() => document.addEventListener('keydown', handleKeydown))
onUnmounted(() => document.removeEventListener('keydown', handleKeydown))

defineExpose({ openPalette })
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[15vh]">
                <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="closePalette" />
                <div class="relative z-10 w-full max-w-xl overflow-hidden rounded-2xl bg-base-100 shadow-2xl" style="border: 1px solid oklch(var(--bc) / 0.1);">
                    <!-- Search Input -->
                    <div class="flex items-center gap-3 border-b border-base-200 px-4 py-3">
                        <MagnifyingGlassIcon class="w-5 h-5 text-base-content/45" />
                        <input
                            ref="inputEl"
                            v-model="query"
                            type="text"
                            placeholder="Search students, exams, classes..."
                            class="flex-1 bg-transparent text-sm outline-none placeholder-base-content/45"
                        />
                        <kbd class="hidden sm:inline-block rounded-md border border-base-300 bg-base-200 px-1.5 py-0.5 text-[10px] font-bold text-base-content/50">ESC</kbd>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="py-8 text-center text-sm text-base-content/50">
                        Searching...
                    </div>

                    <!-- Quick Actions (when empty) -->
                    <div v-else-if="!query && quickActions.length" class="max-h-[50vh] overflow-y-auto p-2">
                        <p class="px-2 py-1 text-[10px] font-bold uppercase tracking-widest text-base-content/40">Quick Actions</p>
                        <button
                            v-for="(action, i) in quickActions"
                            :key="i"
                            @click="navigate(action.url)"
                            @mouseenter="selectedIndex = i"
                            class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                            :class="selectedIndex === i ? 'bg-primary/10 text-primary' : 'hover:bg-base-200'"
                        >
                            <component :is="iconMap[action.icon] || DocumentTextIcon" class="w-4 h-4 text-base-content/55" />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ action.title }}</p>
                                <p class="text-xs text-base-content/50 truncate">{{ action.subtitle }}</p>
                            </div>
                        </button>
                    </div>

                    <!-- No Results -->
                    <div v-else-if="query.length >= 2 && !results.length" class="py-10 text-center">
                        <p class="text-sm text-base-content/50">No results found</p>
                        <p class="text-xs text-base-content/40 mt-1">Try different keywords</p>
                    </div>

                    <!-- Search Results -->
                    <div v-else-if="results.length" class="max-h-[55vh] overflow-y-auto p-2">
                        <template v-for="group in results" :key="group.label">
                            <p v-if="group.items.length" class="px-2 pt-2 pb-1 text-[10px] font-bold uppercase tracking-widest text-base-content/40">{{ group.label }}</p>
                            <button
                                v-for="(item, i) in group.items"
                                :key="`${group.label}-${i}`"
                                @click="navigate(item.url)"
                                @mouseenter="selectedIndex = flatItems.indexOf(item)"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors"
                                :class="flatItems[selectedIndex] === item ? 'bg-primary/10 text-primary' : 'hover:bg-base-200'"
                            >
                                <component :is="iconMap[item.icon] || DocumentTextIcon" class="w-4 h-4 shrink-0 text-base-content/55" />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate">{{ item.title }}</p>
                                    <p class="text-xs text-base-content/50 truncate">{{ item.subtitle }}</p>
                                </div>
                            </button>
                        </template>
                    </div>

                    <!-- Footer -->
                    <div class="flex items-center justify-between gap-2 border-t border-base-200 px-4 py-2 text-[10px] text-base-content/50">
                        <div class="flex gap-3">
                            <span><kbd class="rounded border border-base-300 bg-base-200 px-1 py-0.5 font-bold">↑↓</kbd> navigate</span>
                            <span><kbd class="rounded border border-base-300 bg-base-200 px-1 py-0.5 font-bold">↵</kbd> select</span>
                        </div>
                        <span>Press <kbd class="rounded border border-base-300 bg-base-200 px-1 py-0.5 font-bold">⌘K</kbd> anytime</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
